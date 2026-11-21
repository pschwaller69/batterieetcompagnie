<?php
declare(strict_types=1);

/**
 * Relève de la boîte d'envoi (info@m…) : classement des messages reçus et actions associées.
 *
 *  bounce_hard  rebond définitif (adresse inexistante…)   → contact « en erreur »
 *  bounce_soft  rebond temporaire (boîte pleine…)          → compteur ; « en erreur » au 3e
 *  complaint    signalement « spam » (rapport ARF)         → contact désinscrit
 *  unsubscribe  mail « desinscription » (lien mailto:)     → contact désinscrit
 *  autoreply    réponse automatique / absence              → classé, non transféré
 *  forward      vrai message                               → transféré à l'adresse de l'association
 */

const INBOX_ARCHIVE_FOLDER = 'Archives-mailing';
const INBOX_MAX_PER_RUN = 30;

/* ===================== Analyse MIME ===================== */

function mime_decode_header(string $v): string
{
    if (!str_contains($v, '=?')) {
        return trim($v);
    }
    $d = @iconv_mime_decode($v, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
    return trim($d !== false ? $d : mb_decode_mimeheader($v));
}

/** @return array{headers: array<string,string[]>, raw_headers: string, body: string} */
function mime_split(string $raw): array
{
    $raw = str_replace("\r\n", "\n", $raw);
    $pos = strpos($raw, "\n\n");
    $head = $pos === false ? $raw : substr($raw, 0, $pos);
    $body = $pos === false ? '' : substr($raw, $pos + 2);
    $head = preg_replace("/\n[ \t]+/", ' ', $head); // dépliage
    $headers = [];
    foreach (explode("\n", $head) as $line) {
        if (preg_match('/^([!-9;-~]+):\s*(.*)$/', $line, $m)) {
            $headers[strtolower($m[1])][] = $m[2];
        }
    }
    return ['headers' => $headers, 'raw_headers' => $head, 'body' => $body];
}

function mime_header(array $part, string $name): string
{
    return mime_decode_header((string)($part['headers'][strtolower($name)][0] ?? ''));
}

/** @return array{0:string, 1:array<string,string>} type en minuscules + paramètres */
function mime_ctype(string $v): array
{
    $bits = preg_split('/;(?=(?:[^"]*"[^"]*")*[^"]*$)/', $v);
    $type = strtolower(trim((string)array_shift($bits))) ?: 'text/plain';
    $params = [];
    foreach ($bits as $b) {
        if (preg_match('/^\s*([^=\s]+)\s*=\s*"?(.*?)"?\s*$/s', $b, $m)) {
            $params[strtolower($m[1])] = $m[2];
        }
    }
    return [$type, $params];
}

/**
 * Analyse récursive d'un message.
 * @return array{headers:array, type:string, params:array, body:string, parts:array}
 */
function mime_parse(string $raw, int $depth = 0): array
{
    $p = mime_split($raw);
    [$type, $params] = mime_ctype($p['headers']['content-type'][0] ?? 'text/plain');
    $node = ['headers' => $p['headers'], 'raw_headers' => $p['raw_headers'], 'type' => $type, 'params' => $params, 'body' => '', 'parts' => []];

    if (str_starts_with($type, 'multipart/') && !empty($params['boundary']) && $depth < 8) {
        $b = preg_quote($params['boundary'], '/');
        $chunks = preg_split('/^--' . $b . '(?:--)?[ \t]*$/m', $p['body']);
        array_shift($chunks); // préambule
        foreach ($chunks as $c) {
            $c = ltrim($c, "\n");
            if (trim($c) === '') {
                continue;
            }
            $node['parts'][] = mime_parse(rtrim($c, "\n"), $depth + 1);
        }
        return $node;
    }

    $enc = strtolower(trim((string)($p['headers']['content-transfer-encoding'][0] ?? '')));
    $body = $p['body'];
    if ($enc === 'base64') {
        $body = (string)base64_decode(preg_replace('/\s+/', '', $body));
    } elseif ($enc === 'quoted-printable') {
        $body = quoted_printable_decode($body);
    }
    if (str_starts_with($type, 'text/')) {
        $cs = strtolower($params['charset'] ?? 'utf-8');
        if (!in_array($cs, ['utf-8', 'utf8', 'us-ascii'], true)) {
            $conv = @mb_convert_encoding($body, 'UTF-8', $cs === 'iso-8859-1' ? 'Windows-1252' : $cs);
            $body = $conv !== false ? $conv : $body;
        } elseif (!mb_check_encoding($body, 'UTF-8')) {
            $body = mb_convert_encoding($body, 'UTF-8', 'Windows-1252');
        }
    }
    $node['body'] = $body;
    if ($type === 'message/rfc822' && $depth < 8) {
        $node['parts'][] = mime_parse($body, $depth + 1);
    }
    return $node;
}

/** Premier sous-élément d'un type donné (parcours en profondeur). */
function mime_find(array $node, string $type): ?array
{
    if ($node['type'] === $type && $node['body'] !== '') {
        return $node;
    }
    foreach ($node['parts'] as $p) {
        if ($r = mime_find($p, $type)) {
            return $r;
        }
    }
    return null;
}

function mime_text(array $msg): string
{
    if ($t = mime_find($msg, 'text/plain')) {
        return trim($t['body']);
    }
    if ($h = mime_find($msg, 'text/html')) {
        return html_to_text(preg_replace('#<(script|style|head)\b.*?</\1>#is', '', $h['body']));
    }
    return trim($msg['body']);
}

/** Adresse e-mail extraite d'un en-tête « Nom <adresse> ». */
function addr_of(string $v): string
{
    if (preg_match('/<([^<>@\s]+@[^<>\s]+)>/', $v, $m)) {
        return normalize_email($m[1]);
    }
    if (preg_match('/([^\s<>"\'(),;:]+@[^\s<>"\'(),;:]+)/', $v, $m)) {
        return normalize_email($m[1]);
    }
    return '';
}

/** Blocs « champ: valeur » d'un message/delivery-status. */
function dsn_blocks(string $body): array
{
    $blocks = [];
    foreach (preg_split("/\n\s*\n/", str_replace("\r\n", "\n", trim($body))) as $chunk) {
        $chunk = preg_replace("/\n[ \t]+/", ' ', $chunk);
        $f = [];
        foreach (explode("\n", $chunk) as $line) {
            if (preg_match('/^([A-Za-z-]+):\s*(.*)$/', $line, $m)) {
                $f[strtolower($m[1])] = trim($m[2]);
            }
        }
        if ($f) {
            $blocks[] = $f;
        }
    }
    return $blocks;
}

/* ===================== Classement ===================== */

/**
 * @return array{kind:string, recipients:array<int,array{email:string,status:string,diag:string}>, from:string, subject:string}
 */
function inbox_classify(array $msg): array
{
    $from = addr_of(mime_header($msg, 'from'));
    $subject = mime_header($msg, 'subject');
    $res = ['kind' => 'forward', 'recipients' => [], 'from' => $from, 'subject' => $subject];

    // 1. Signalement de spam (Feedback Loop, RFC 5965)
    if ($msg['type'] === 'multipart/report' && strtolower($msg['params']['report-type'] ?? '') === 'feedback-report') {
        $res['kind'] = 'complaint';
        $orig = mime_find($msg, 'message/rfc822') ?? mime_find($msg, 'text/rfc822-headers');
        $to = '';
        if ($orig) {
            $o = $orig['parts'][0] ?? mime_split($orig['body']);
            $to = addr_of(mime_decode_header((string)($o['headers']['to'][0] ?? '')));
        }
        if ($to !== '') {
            $res['recipients'][] = ['email' => $to, 'status' => 'complaint', 'diag' => 'Signalé comme indésirable'];
        }
        return $res;
    }

    // 2. Rebond standard (DSN, RFC 3464)
    $dsn = mime_find($msg, 'message/delivery-status');
    if ($dsn || ($msg['type'] === 'multipart/report' && strtolower($msg['params']['report-type'] ?? '') === 'delivery-status')) {
        $blocks = $dsn ? dsn_blocks($dsn['body']) : [];
        foreach ($blocks as $f) {
            $rcpt = $f['final-recipient'] ?? $f['original-recipient'] ?? '';
            if ($rcpt === '') {
                continue;
            }
            $action = strtolower($f['action'] ?? 'failed');
            if (in_array($action, ['delivered', 'relayed', 'expanded'], true)) {
                continue;
            }
            $email = addr_of(preg_replace('/^[^;]*;\s*/', '', $rcpt));
            if ($email === '') {
                continue;
            }
            $status = $f['status'] ?? '';
            $soft = $action === 'delayed' || str_starts_with($status, '4');
            $res['recipients'][] = ['email' => $email, 'status' => $soft ? 'soft' : 'hard',
                'diag' => trim(($status ? "$status " : '') . ($f['diagnostic-code'] ?? $action))];
        }
        if ($res['recipients']) {
            $res['kind'] = in_array('hard', array_column($res['recipients'], 'status'), true) ? 'bounce_hard' : 'bounce_soft';
            return $res;
        }
    }

    // 3. Rebond non standard (texte libre envoyé par MAILER-DAEMON / postmaster)
    $daemon = (bool)preg_match('/^(mailer-daemon|postmaster|mail-daemon|bounce)/i', $from)
        || (bool)preg_match('/(undeliver|delivery (status|failure|has failed)|returned mail|mail delivery failed|non remis|non distribu|échec de (la )?(remise|distribution)|failure notice)/iu', $subject);
    if ($daemon) {
        $text = mime_text($msg);
        $own = [normalize_email(setting('from_email')), normalize_email(setting('smtp_user'))];
        $hard = (bool)preg_match('/\b5\.\d{1,3}\.\d{1,3}\b|\b55[0-4]\b|user unknown|does not exist|no such user|introuvable|n\'existe pas|inconnu/iu', $text);
        if (preg_match_all('/[A-Za-z0-9._%+\'-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $text, $m)) {
            foreach (array_unique(array_map('normalize_email', $m[0])) as $e) {
                if (in_array($e, $own, true) || preg_match('/^(mailer-daemon|postmaster)@/i', $e)) {
                    continue;
                }
                if (q_val('SELECT 1 FROM contacts WHERE email = ?', [$e])) {
                    $res['recipients'][] = ['email' => $e, 'status' => $hard ? 'hard' : 'soft', 'diag' => mb_substr(preg_replace('/\s+/', ' ', $text), 0, 200)];
                }
            }
        }
        $res['kind'] = $res['recipients'] ? ($hard ? 'bounce_hard' : 'bounce_soft') : 'autoreply';
        return $res;
    }

    // 4. Désinscription par mail (lien mailto: de l'en-tête List-Unsubscribe)
    if (preg_match('/^\s*(re\s*:\s*)?d[ée]sinscri(ption|re)\b/iu', $subject)) {
        $res['kind'] = 'unsubscribe';
        return $res;
    }

    // 5. Réponses automatiques
    $auto = strtolower(mime_header($msg, 'auto-submitted'));
    if (($auto !== '' && $auto !== 'no')
        || isset($msg['headers']['x-autoreply']) || isset($msg['headers']['x-autorespond'])
        || preg_match('/^(auto_reply|bulk|junk)$/i', mime_header($msg, 'precedence'))
        || preg_match('/^(r[ée]ponse automatique|automatic reply|auto(matische)?[ -]?(reply|antwort)|out of (the )?office|absence|absent|auto:|accus[ée] de r[ée]ception)/iu', $subject)) {
        $res['kind'] = 'autoreply';
        return $res;
    }

    return $res;
}

/* ===================== Actions ===================== */

function inbox_apply(array $c, array $msg, string $raw, ?SmtpSender $sender): string
{
    switch ($c['kind']) {
        case 'bounce_hard':
        case 'bounce_soft':
            $out = [];
            foreach ($c['recipients'] as $r) {
                $ct = q_one('SELECT id, status, soft_bounces FROM contacts WHERE email = ?', [$r['email']]);
                if (!$ct) {
                    $out[] = $r['email'] . ' (inconnu)';
                    continue;
                }
                $hard = $r['status'] === 'hard' || (int)$ct['soft_bounces'] + 1 >= 3;
                q('UPDATE contacts SET soft_bounces = soft_bounces + ?, status = CASE WHEN ? AND status = \'subscribed\' THEN \'bounced\' ELSE status END, updated_at = ? WHERE id = ?',
                    [$r['status'] === 'soft' ? 1 : 0, $hard ? 1 : 0, now(), $ct['id']]);
                if ($hard) {
                    q("UPDATE messages SET status = 'failed', last_error = ? WHERE id = (SELECT id FROM messages WHERE contact_id = ? AND status = 'sent' ORDER BY sent_at DESC LIMIT 1)",
                        ['Rebond : ' . mb_substr($r['diag'], 0, 300), $ct['id']]);
                }
                $out[] = $r['email'] . ($hard ? ' → en erreur' : ' (temporaire, ' . ((int)$ct['soft_bounces'] + 1) . '/3)');
            }
            return implode(' ; ', $out);

        case 'complaint':
        case 'unsubscribe':
            $emails = $c['kind'] === 'complaint' ? array_column($c['recipients'], 'email') : [$c['from']];
            $out = [];
            foreach (array_filter($emails) as $e) {
                $n = q("UPDATE contacts SET status = 'unsubscribed', unsubscribed_at = COALESCE(unsubscribed_at, ?), updated_at = ? WHERE email = ? AND status <> 'unsubscribed'",
                    [now(), now(), $e])->rowCount();
                q("UPDATE messages SET status = 'skipped', last_error = 'Désinscrit' WHERE status = 'pending' AND contact_id = (SELECT id FROM contacts WHERE email = ?)", [$e]);
                $out[] = $e . ($n ? ' → désinscrit' : (q_val('SELECT 1 FROM contacts WHERE email = ?', [$e]) ? ' (déjà désinscrit)' : ' (inconnu)'));
            }
            return implode(' ; ', $out) ?: 'aucun destinataire identifié';

        case 'forward':
            if (!$sender) {
                return 'non transféré (aucune adresse de transfert)';
            }
            inbox_forward($sender, $msg, $raw);
            return 'transféré à ' . setting('inbox_forward_to');

        default:
            return 'classé';
    }
}

function inbox_forward(SmtpSender $sender, array $msg, string $raw): void
{
    $to = setting('inbox_forward_to');
    $fromHdr = mime_header($msg, 'from');
    $fromAddr = addr_of($fromHdr);
    $subject = mime_header($msg, 'subject') ?: '(sans objet)';
    $date = mime_header($msg, 'date');

    $m = $sender->fresh();
    $m->setFrom(setting('from_email'), 'Mailing ' . setting('org_name'), false);
    $m->Sender = setting('from_email');
    $m->addAddress($to);
    if ($fromAddr !== '' && valid_email($fromAddr)) {
        $name = trim(preg_replace('/<[^>]*>/', '', $fromHdr), " \"'");
        $m->addReplyTo($fromAddr, $name !== $fromAddr ? $name : '');
    }
    $m->Subject = '[Mailing] ' . $subject;
    $m->addCustomHeader('X-BC-Forwarded', '1');
    $m->addCustomHeader('Auto-Submitted', 'auto-forwarded');

    $intro = "Message reçu sur " . setting('from_email') . " (réponse à un mailing), transféré automatiquement.\n"
        . "Répondre à ce mail écrit directement à l'expéditeur.\n\n"
        . "De : $fromHdr\nDate : $date\nObjet : $subject\n"
        . str_repeat('-', 40) . "\n\n";
    $text = mime_text($msg);
    $html = mime_find($msg, 'text/html');

    $m->isHTML(true);
    $m->Body = '<div style="font:13px Arial,sans-serif;color:#555;background:#f4f4f4;padding:10px 12px;border-radius:6px;margin-bottom:14px;">'
        . nl2br(h(trim($intro, "-\n "))) . '</div>'
        . ($html ? preg_replace('#<script\b.*?</script>#is', '', $html['body']) : nl2br(h($text)));
    $m->AltBody = $intro . $text;
    $m->addStringAttachment($raw, 'message-original.eml', 'base64', 'message/rfc822');
    $m->send();
    q('INSERT INTO send_log (sent_at, kind) VALUES (?, ?)', [time(), 'forward']);
}

/* ===================== Relève ===================== */

function inbox_configured(): bool
{
    return setting('inbox_enabled') === '1' && setting('smtp_host') !== '' && setting('smtp_user') !== '';
}

/**
 * Relève et traite les messages non lus.
 * @return array{processed:int, counts:array<string,int>, message:string}
 */
function inbox_process(): array
{
    $res = ['processed' => 0, 'counts' => [], 'message' => ''];
    if (!inbox_configured()) {
        $res['message'] = 'Relève de la boîte désactivée.';
        return $res;
    }
    $lockFile = dirname(config('db_path', APP_ROOT . '/data/x')) . '/inbox.lock';
    $lock = fopen($lockFile, 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        $res['message'] = 'Relève déjà en cours.';
        return $res;
    }
    $imap = new ImapClient(
        setting('inbox_host') ?: setting('smtp_host'),
        (int)(setting('inbox_port') ?: 993),
        setting('inbox_secure') ?: 'ssl'
    );
    $sender = null;
    try {
        $imap->connect(setting('smtp_user'), setting('smtp_pass'));
        $imap->select('INBOX');
        $forwardTo = normalize_email(setting('inbox_forward_to'));
        $own = array_filter([normalize_email(setting('from_email')), $forwardTo]);

        foreach (array_slice($imap->unseen(), 0, INBOX_MAX_PER_RUN) as $uid) {
            $raw = $imap->fetch($uid);
            if ($raw === '') {
                continue;
            }
            $msg = mime_parse($raw);
            $c = inbox_classify($msg);

            // Garde-fous anti-boucle : nos propres transferts / messages venant de l'adresse de transfert
            if ($c['kind'] === 'forward' && (isset($msg['headers']['x-bc-forwarded']) || in_array($c['from'], $own, true))) {
                $c['kind'] = 'autoreply';
            }
            if ($c['kind'] === 'forward') {
                if ($forwardTo === '' || !valid_email($forwardTo)) {
                    $c['kind'] = 'kept';
                } elseif (hourly_remaining() <= 0) {
                    $res['message'] = 'Plafond horaire atteint : transfert reporté.';
                    break; // message laissé non lu, repris au prochain passage
                } else {
                    $sender ??= new SmtpSender();
                }
            }

            $detail = $c['kind'] === 'kept' ? 'laissé dans la boîte (aucune adresse de transfert)' : inbox_apply($c, $msg, $raw, $sender);
            q('INSERT INTO inbox_log (uid, received_at, from_addr, subject, kind, detail) VALUES (?, ?, ?, ?, ?, ?)', [
                $uid,
                ($t = strtotime(mime_header($msg, 'date'))) ? date('Y-m-d H:i:s', $t) : now(),
                mb_substr($c['from'], 0, 200), mb_substr($c['subject'], 0, 300), $c['kind'], mb_substr($detail, 0, 1000),
            ]);

            $imap->markSeen($uid);
            if ($c['kind'] !== 'kept' && setting('inbox_archive') === '1') {
                $imap->moveTo($uid, INBOX_ARCHIVE_FOLDER);
            }
            $res['processed']++;
            $res['counts'][$c['kind']] = ($res['counts'][$c['kind']] ?? 0) + 1;
        }
    } catch (\Throwable $e) {
        $res['message'] = 'Relève de la boîte en échec : ' . $e->getMessage();
        if (setting('inbox_last_result') !== $res['message']) { // pas de répétition toutes les 5 min
            log_activity($res['message'], 'error');
        }
    } finally {
        $sender?->close();
        $imap->logout();
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    q("DELETE FROM inbox_log WHERE id <= (SELECT MAX(id) - 3000 FROM inbox_log)");
    save_setting('inbox_last_run', now());

    if ($res['processed']) {
        $parts = [];
        foreach ($res['counts'] as $k => $n) {
            $parts[] = "$n " . mb_strtolower(inbox_kind_label($k));
        }
        $msgTxt = 'Boîte de réception : ' . implode(', ', $parts) . '.';
        log_activity($msgTxt);
        $res['message'] = $res['message'] ?: $msgTxt;
    }
    $res['message'] = $res['message'] ?: 'Aucun nouveau message.';
    save_setting('inbox_last_result', $res['message']);
    return $res;
}

function inbox_kind_label(string $k): string
{
    return [
        'bounce_hard' => 'Rebond définitif',
        'bounce_soft' => 'Rebond temporaire',
        'complaint' => 'Signalement spam',
        'unsubscribe' => 'Désinscription',
        'autoreply' => 'Réponse automatique',
        'forward' => 'Transféré',
        'kept' => 'Conservé',
    ][$k] ?? $k;
}
