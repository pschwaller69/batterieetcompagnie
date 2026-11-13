<?php
declare(strict_types=1);

/**
 * Destinataires d'une campagne : contacts abonnés des listes ciblées (ou tous).
 */
function campaign_recipients_sql(array $campaign): array
{
    if ((int)$campaign['target_all'] === 1) {
        return ["SELECT c.* FROM contacts c WHERE c.status = 'subscribed'", []];
    }
    return ["SELECT DISTINCT c.* FROM contacts c
             JOIN contact_list cl ON cl.contact_id = c.id
             JOIN campaign_lists k ON k.list_id = cl.list_id
             WHERE c.status = 'subscribed' AND k.campaign_id = ?", [$campaign['id']]];
}

function count_recipients(array $campaign): int
{
    [$sql, $p] = campaign_recipients_sql($campaign);
    return (int)q_val("SELECT COUNT(*) FROM ($sql)", $p);
}

/**
 * Crée la file de messages d'une campagne et la passe en "sending" ou "scheduled".
 */
function enqueue_campaign(int $id, ?string $scheduledAt = null): int
{
    $c = q_one('SELECT * FROM campaigns WHERE id = ?', [$id]);
    if (!$c || !in_array($c['status'], ['draft'], true)) {
        throw new RuntimeException('Cette campagne ne peut plus être envoyée.');
    }
    [$sql, $p] = campaign_recipients_sql($c);
    $pdo = db();
    $pdo->beginTransaction();
    $ins = $pdo->prepare('INSERT OR IGNORE INTO messages (campaign_id, contact_id, email, token) VALUES (?, ?, ?, ?)');
    $n = 0;
    foreach (q($sql, $p)->fetchAll() as $ct) {
        $ins->execute([$id, $ct['id'], $ct['email'], random_token(18)]);
        $n += $ins->rowCount();
    }
    $status = $scheduledAt ? 'scheduled' : 'sending';
    q('UPDATE campaigns SET status = ?, scheduled_at = ?, queued_at = ?, updated_at = ? WHERE id = ?',
        [$status, $scheduledAt, now(), now(), $id]);
    $pdo->commit();
    log_activity("Campagne « {$c['name']} » mise en file : $n destinataire(s)" . ($scheduledAt ? " — programmée le " . fmt_date($scheduledAt) : ''));
    return $n;
}

function sent_last_hour(): int
{
    return (int)q_val('SELECT COUNT(*) FROM send_log WHERE sent_at > ?', [time() - 3600]);
}

function hourly_remaining(): int
{
    return max(0, (int)setting('hourly_limit') - sent_last_hour());
}

/**
 * Traite un lot de la file. Appelé par le cron (CLI ou URL) ou depuis l'interface.
 * @return array{sent:int, failed:int, skipped:int, remaining:int, message:string}
 */
function process_queue(?int $maxOverride = null): array
{
    $res = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'remaining' => 0, 'message' => ''];

    $lockFile = dirname(config('db_path', APP_ROOT . '/data/x')) . '/queue.lock';
    $lock = fopen($lockFile, 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        $res['message'] = 'Un envoi est déjà en cours.';
        return $res;
    }

    try {
        // Campagnes programmées arrivées à échéance
        q("UPDATE campaigns SET status = 'sending', updated_at = ? WHERE status = 'scheduled' AND scheduled_at <= ?", [now(), now()]);

        if (setting('smtp_host') === '') {
            $res['message'] = 'SMTP non configuré.';
            return $res;
        }

        $allow = min($maxOverride ?? (int)setting('per_run'), hourly_remaining());
        $pendingTotal = (int)q_val("SELECT COUNT(*) FROM messages m JOIN campaigns c ON c.id = m.campaign_id
                                    WHERE m.status = 'pending' AND c.status = 'sending'");
        if ($pendingTotal === 0) {
            $res['message'] = 'Rien à envoyer.';
            finish_campaigns();
            return $res;
        }
        if ($allow <= 0) {
            $res['remaining'] = $pendingTotal;
            $res['message'] = 'Plafond horaire atteint (' . setting('hourly_limit') . '/h). Reprise automatique au prochain passage.';
            return $res;
        }

        $batch = q("SELECT m.*, c.status AS c_status FROM messages m JOIN campaigns c ON c.id = m.campaign_id
                    WHERE m.status = 'pending' AND c.status = 'sending'
                    ORDER BY m.campaign_id, m.id LIMIT ?", [$allow])->fetchAll();

        $campaigns = [];
        $sender = new SmtpSender();
        $delay = max(0, (int)setting('delay_ms')) * 1000;
        $first = true;

        foreach ($batch as $msg) {
            $cid = (int)$msg['campaign_id'];
            $campaigns[$cid] ??= q_one('SELECT * FROM campaigns WHERE id = ?', [$cid]);
            $camp = $campaigns[$cid];
            $contact = $msg['contact_id'] ? q_one('SELECT * FROM contacts WHERE id = ?', [$msg['contact_id']]) : null;

            if (!$contact || $contact['status'] !== 'subscribed') {
                q("UPDATE messages SET status = 'skipped', last_error = 'Contact désinscrit ou supprimé' WHERE id = ?", [$msg['id']]);
                $res['skipped']++;
                continue;
            }
            if (!$first && $delay) {
                usleep($delay);
            }
            $first = false;

            try {
                $built = build_email($camp, $contact, $msg['token']);
                $sender->send($contact['email'], trim($contact['first_name'] . ' ' . $contact['last_name']), $built, $camp);
                q("UPDATE messages SET status = 'sent', sent_at = ?, attempts = attempts + 1, last_error = NULL WHERE id = ?", [now(), $msg['id']]);
                q('INSERT INTO send_log (sent_at, kind) VALUES (?, ?)', [time(), 'campaign']);
                $res['sent']++;
            } catch (\Throwable $e) {
                $err = mb_substr($e->getMessage(), 0, 500);
                $attempts = (int)$msg['attempts'] + 1;
                // Problème côté serveur/compte (quota, connexion, authentification) : on arrête le lot
                // sans pénaliser le destinataire.
                $stopRun = (bool)preg_match('/quota|limit|rate|too many|\[SMTP 421|could not connect|connect\(\)|authenticat/i', $err);
                // Adresse inexistante : erreur définitive, contact marqué "en erreur"
                $badAddress = !$stopRun && (bool)preg_match('/\[SMTP 55[0-3]|\b5\.1\.\d\b|user unknown|unknown user|does not exist|no such user|invalid address|mailbox unavailable/i', $err);
                // Autre refus définitif (5xx) : échec du message, contact conservé
                $otherPermanent = !$stopRun && !$badAddress && (bool)preg_match('/\[SMTP 5\d\d/', $err);

                if ($stopRun) {
                    q('UPDATE messages SET last_error = ? WHERE id = ?', [$err, $msg['id']]);
                    log_activity('Envoi interrompu : ' . $err, 'error');
                    $res['message'] = 'Envoi interrompu : ' . $err;
                    break;
                }
                if ($badAddress) {
                    q("UPDATE messages SET status = 'failed', attempts = ?, last_error = ? WHERE id = ?", [$attempts, $err, $msg['id']]);
                    q("UPDATE contacts SET status = 'bounced', updated_at = ? WHERE id = ?", [now(), $contact['id']]);
                    $res['failed']++;
                } elseif ($otherPermanent || $attempts >= 3) {
                    q("UPDATE messages SET status = 'failed', attempts = ?, last_error = ? WHERE id = ?", [$attempts, $err, $msg['id']]);
                    $res['failed']++;
                } else {
                    // Erreur temporaire (ex. 450 greylisting) : nouvel essai au prochain passage
                    q('UPDATE messages SET attempts = ?, last_error = ? WHERE id = ?', [$attempts, $err, $msg['id']]);
                }
            }
        }
        $sender->close();
        finish_campaigns();

        $res['remaining'] = (int)q_val("SELECT COUNT(*) FROM messages m JOIN campaigns c ON c.id = m.campaign_id
                                        WHERE m.status = 'pending' AND c.status = 'sending'");
        if ($res['message'] === '') {
            $res['message'] = "{$res['sent']} envoyé(s), {$res['failed']} échec(s), {$res['skipped']} ignoré(s), {$res['remaining']} restant(s).";
        }
        if ($res['sent'] || $res['failed']) {
            log_activity('Lot traité : ' . $res['message']);
        }
        return $res;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function finish_campaigns(): void
{
    foreach (q("SELECT id, name FROM campaigns WHERE status = 'sending'")->fetchAll() as $c) {
        $left = (int)q_val("SELECT COUNT(*) FROM messages WHERE campaign_id = ? AND status = 'pending'", [$c['id']]);
        if ($left === 0) {
            q("UPDATE campaigns SET status = 'sent', finished_at = ?, updated_at = ? WHERE id = ?", [now(), now(), $c['id']]);
            log_activity("Campagne « {$c['name']} » terminée.");
        }
    }
}

function campaign_stats(int $id): array
{
    $r = q_one("SELECT COUNT(*) AS total,
        SUM(status = 'pending') AS pending,
        SUM(status = 'sent') AS sent,
        SUM(status = 'failed') AS failed,
        SUM(status = 'skipped') AS skipped,
        SUM(opened_at IS NOT NULL) AS opened
        FROM messages WHERE campaign_id = ?", [$id]);
    return array_map('intval', $r ?? []);
}
