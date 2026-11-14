<?php
declare(strict_types=1);

/**
 * Synchronisation des contacts Outlook (Microsoft 365) via Microsoft Graph.
 *
 * Authentification « application » (client credentials) : aucun compte utilisateur,
 * l'application Entra lit les contacts des boîtes autorisées par l'administrateur.
 */

const M365_GRAPH = 'https://graph.microsoft.com/v1.0';
const M365_CATEGORY_PREFIX = 'Outlook · ';
const M365_SELECT = 'id,givenName,surname,displayName,emailAddresses,mobilePhone,businessPhones,homePhones,categories';

function m365_configured(): bool
{
    return setting('m365_tenant') !== '' && setting('m365_client_id') !== ''
        && setting('m365_client_secret') !== '' && m365_mailboxes() !== [];
}

function m365_mailboxes(): array
{
    $out = [];
    foreach (preg_split('/[\s,;]+/', setting('m365_mailboxes')) as $m) {
        $m = normalize_email($m);
        if ($m !== '' && valid_email($m)) {
            $out[] = $m;
        }
    }
    return array_values(array_unique($out));
}

/**
 * Requête HTTP. Remplaçable en test via $GLOBALS['M365_HTTP'] = fn($method, $url, $headers, $body) => [status, bodyString].
 * @return array{0:int,1:array}
 */
function m365_http(string $method, string $url, array $headers = [], ?string $body = null): array
{
    if (isset($GLOBALS['M365_HTTP']) && is_callable($GLOBALS['M365_HTTP'])) {
        [$status, $raw] = ($GLOBALS['M365_HTTP'])($method, $url, $headers, $body);
    } else {
        if (!function_exists('curl_init')) {
            throw new RuntimeException("L'extension PHP curl est nécessaire pour la synchronisation Microsoft 365.");
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Connexion à Microsoft impossible : ' . $err);
        }
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
    }
    $json = json_decode((string)$raw, true);
    return [(int)$status, is_array($json) ? $json : []];
}

function m365_token(bool $force = false): string
{
    $cached = setting('m365_token');
    $exp = (int)setting('m365_token_exp');
    if (!$force && $cached !== '' && $exp > time() + 120) {
        return decrypt_value($cached);
    }
    $tenant = rawurlencode(setting('m365_tenant'));
    [$status, $j] = m365_http('POST', "https://login.microsoftonline.com/$tenant/oauth2/v2.0/token",
        ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => setting('m365_client_id'),
            'client_secret' => setting('m365_client_secret'),
            'scope' => 'https://graph.microsoft.com/.default',
        ]));
    if ($status !== 200 || empty($j['access_token'])) {
        $desc = $j['error_description'] ?? ($j['error'] ?? "HTTP $status");
        $desc = strtok((string)$desc, "\r\n");
        $hint = '';
        if (str_contains((string)$desc, 'AADSTS7000215')) {
            $hint = ' — secret client invalide : copiez la « Valeur » du secret, pas son ID.';
        } elseif (str_contains((string)$desc, 'AADSTS700016')) {
            $hint = ' — ID d\'application introuvable dans ce tenant.';
        } elseif (str_contains((string)$desc, 'AADSTS90002')) {
            $hint = ' — ID de tenant (annuaire) incorrect.';
        } elseif (str_contains((string)$desc, 'AADSTS7000222')) {
            $hint = ' — le secret client a expiré : créez-en un nouveau dans Entra.';
        }
        throw new RuntimeException('Authentification Microsoft refusée : ' . $desc . $hint);
    }
    save_setting('m365_token', encrypt_value($j['access_token']));
    save_setting('m365_token_exp', (string)(time() + (int)($j['expires_in'] ?? 3600)));
    return $j['access_token'];
}

/**
 * GET Graph avec suivi de la pagination (@odata.nextLink).
 */
function m365_get_all(string $url): array
{
    $items = [];
    $guard = 0;
    while ($url !== '' && $guard++ < 200) {
        [$status, $j] = m365_http('GET', $url, ['Authorization: Bearer ' . m365_token(), 'Accept: application/json']);
        if ($status === 401) {
            m365_token(true);
            [$status, $j] = m365_http('GET', $url, ['Authorization: Bearer ' . m365_token(), 'Accept: application/json']);
        }
        if ($status !== 200) {
            $code = $j['error']['code'] ?? "HTTP $status";
            $msg = $j['error']['message'] ?? '';
            $hint = '';
            if ($status === 403 || $code === 'ErrorAccessDenied') {
                $hint = ' — l\'application n\'a pas accès à cette boîte (autorisation Contacts.Read / périmètre Exchange, voir README).';
            } elseif ($code === 'ErrorInvalidUser' || $status === 404) {
                $hint = ' — boîte introuvable dans ce tenant.';
            }
            throw new RuntimeException("Microsoft Graph : $code $msg$hint");
        }
        foreach ($j['value'] ?? [] as $v) {
            $items[] = $v;
        }
        $url = (string)($j['@odata.nextLink'] ?? '');
    }
    return $items;
}

/**
 * Dossiers de contacts d'une boîte (y compris sous-dossiers), avec chemin lisible.
 * Le dossier par défaut « Contacts » est représenté par l'id '__default'.
 * @return array<int, array{id:string, path:string}>
 */
function m365_folders(string $mailbox): array
{
    $mb = rawurlencode($mailbox);
    $out = [['id' => '__default', 'path' => 'Contacts']];
    $walk = function (string $url, string $prefix, int $depth) use (&$walk, &$out, $mb) {
        if ($depth > 5) {
            return;
        }
        foreach (m365_get_all($url) as $f) {
            $path = $prefix . $f['displayName'];
            $out[] = ['id' => $f['id'], 'path' => $path];
            $walk(M365_GRAPH . "/users/$mb/contactFolders/" . rawurlencode($f['id']) . '/childFolders?$top=100', $path . ' / ', $depth + 1);
        }
    };
    $walk(M365_GRAPH . "/users/$mb/contactFolders?\$top=100", 'Contacts / ', 0);
    return $out;
}

function m365_folder_contacts(string $mailbox, string $folderId): array
{
    $mb = rawurlencode($mailbox);
    $sel = '$select=' . M365_SELECT . '&$top=250';
    $url = $folderId === '__default'
        ? M365_GRAPH . "/users/$mb/contacts?$sel"
        : M365_GRAPH . "/users/$mb/contactFolders/" . rawurlencode($folderId) . "/contacts?$sel";
    return m365_get_all($url);
}

/**
 * Teste la connexion et décrit ce qui serait synchronisé.
 * @return array<string, array> par boîte : liste de [path, count] ou ['error' => msg]
 */
function m365_probe(): array
{
    m365_token(true);
    $report = [];
    foreach (m365_mailboxes() as $mb) {
        try {
            $rows = [];
            foreach (m365_folders($mb) as $f) {
                $rows[] = ['path' => $f['path'], 'count' => count(m365_folder_contacts($mb, $f['id'])), 'selected' => m365_folder_selected($f['path'])];
            }
            $report[$mb] = $rows;
        } catch (\Throwable $e) {
            $report[$mb] = ['error' => $e->getMessage()];
        }
    }
    return $report;
}

/** Filtre de dossiers : vide = tous ; sinon noms (ou chemins) séparés par des virgules. */
function m365_folder_selected(string $path): bool
{
    $filter = array_filter(array_map('trim', explode(',', setting('m365_folders'))));
    if (!$filter) {
        return true;
    }
    $leaf = trim((string)strrchr(' / ' . $path, '/'), '/ ');
    foreach ($filter as $f) {
        if (strcasecmp($f, $path) === 0 || strcasecmp($f, $leaf) === 0) {
            return true;
        }
    }
    return false;
}

function m365_list_id(string $name): int
{
    $name = trim($name);
    q('INSERT OR IGNORE INTO lists (name, description) VALUES (?, ?)', [$name, 'Synchronisée depuis Microsoft 365']);
    return (int)q_val('SELECT id FROM lists WHERE name = ?', [$name]);
}

/**
 * Synchronisation complète.
 * @return array{fetched:int, emails:int, created:int, updated:int, removed:int, skipped_invalid:int, lists:int, message:string}
 */
function m365_sync(): array
{
    if (!m365_configured()) {
        throw new RuntimeException('Synchronisation Microsoft 365 non configurée.');
    }
    $stats = ['fetched' => 0, 'emails' => 0, 'created' => 0, 'updated' => 0, 'removed' => 0, 'skipped_invalid' => 0, 'lists' => 0, 'message' => ''];

    // 1. Collecte (toutes boîtes, dossiers retenus) — aucune écriture tant que tout n'est pas lu
    $people = []; // email => [first, last, phone, categories[]]
    foreach (m365_mailboxes() as $mb) {
        foreach (m365_folders($mb) as $f) {
            if (!m365_folder_selected($f['path'])) {
                continue;
            }
            foreach (m365_folder_contacts($mb, $f['id']) as $c) {
                $stats['fetched']++;
                $first = trim((string)($c['givenName'] ?? ''));
                $last = trim((string)($c['surname'] ?? ''));
                if ($first === '' && $last === '' && !empty($c['displayName'])) {
                    $last = trim((string)$c['displayName']);
                }
                $phone = trim((string)($c['mobilePhone'] ?? ($c['businessPhones'][0] ?? ($c['homePhones'][0] ?? ''))));
                $cats = array_values(array_filter(array_map('trim', (array)($c['categories'] ?? []))));
                foreach ((array)($c['emailAddresses'] ?? []) as $e) {
                    $email = normalize_email((string)($e['address'] ?? ''));
                    if (!valid_email($email)) {
                        $stats['skipped_invalid']++;
                        continue;
                    }
                    if (!isset($people[$email])) {
                        $people[$email] = ['first' => $first, 'last' => $last, 'phone' => $phone, 'cats' => $cats];
                    } else {
                        $p = &$people[$email];
                        $p['first'] = $p['first'] ?: $first;
                        $p['last'] = $p['last'] ?: $last;
                        $p['phone'] = $p['phone'] ?: $phone;
                        $p['cats'] = array_values(array_unique(array_merge($p['cats'], $cats)));
                        unset($p);
                    }
                }
            }
        }
    }
    $stats['emails'] = count($people);

    // 2. Écriture
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $mainList = m365_list_id(setting('m365_list_name') ?: 'Microsoft 365');
        $useCats = setting('m365_categories_as_lists') === '1';
        $managed = array_map('intval', json_decode(setting('m365_category_lists') ?: '[]', true) ?: []);
        $catListIds = [];
        $syncedIds = [];

        foreach ($people as $email => $p) {
            $row = q_one('SELECT id, first_name, last_name, phone FROM contacts WHERE email = ?', [$email]);
            if ($row) {
                $id = (int)$row['id'];
                // Outlook fait foi pour les champs qu'il renseigne ; un champ vide côté Outlook n'efface rien
                $new = [
                    'first_name' => $p['first'] !== '' ? $p['first'] : $row['first_name'],
                    'last_name' => $p['last'] !== '' ? $p['last'] : $row['last_name'],
                    'phone' => $p['phone'] !== '' ? $p['phone'] : $row['phone'],
                ];
                if ($new['first_name'] !== $row['first_name'] || $new['last_name'] !== $row['last_name'] || $new['phone'] !== $row['phone']) {
                    q('UPDATE contacts SET first_name = ?, last_name = ?, phone = ?, updated_at = ? WHERE id = ?',
                        [$new['first_name'], $new['last_name'], $new['phone'], now(), $id]);
                    $stats['updated']++;
                }
            } else {
                q("INSERT INTO contacts (email, first_name, last_name, phone, token, source) VALUES (?, ?, ?, ?, ?, 'm365')",
                    [$email, $p['first'], $p['last'], $p['phone'], random_token(18)]);
                $id = (int)$pdo->lastInsertId();
                $stats['created']++;
            }
            $syncedIds[$id] = true;
            q('INSERT OR IGNORE INTO contact_list (contact_id, list_id) VALUES (?, ?)', [$id, $mainList]);

            if ($useCats) {
                $want = [];
                foreach ($p['cats'] as $cat) {
                    // Préfixe : une catégorie Outlook ne doit jamais s'approprier une liste créée à la main
                    $catListIds[$cat] ??= m365_list_id(M365_CATEGORY_PREFIX . $cat);
                    $want[] = $catListIds[$cat];
                    q('INSERT OR IGNORE INTO contact_list (contact_id, list_id) VALUES (?, ?)', [$id, $catListIds[$cat]]);
                }
                // retire le contact des listes-catégories qu'il n'a plus dans Outlook
                $drop = array_diff(array_unique(array_merge($managed, array_values($catListIds))), $want);
                if ($drop) {
                    q('DELETE FROM contact_list WHERE contact_id = ? AND list_id IN (' . implode(',', array_map('intval', $drop)) . ')', [$id]);
                }
            }
        }

        $allManaged = array_values(array_unique(array_merge($managed, array_values($catListIds))));
        if ($useCats) {
            save_setting('m365_category_lists', json_encode($allManaged));
            $stats['lists'] = count($catListIds);
        }

        // 3. Contacts disparus d'Outlook : retirés des listes synchronisées (jamais supprimés)
        if (setting('m365_remove_missing') === '1') {
            $lists = array_merge([$mainList], $useCats ? $allManaged : []);
            $in = implode(',', array_map('intval', $lists));
            foreach (q("SELECT DISTINCT contact_id FROM contact_list WHERE list_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN) as $cid) {
                if (!isset($syncedIds[(int)$cid])) {
                    q("DELETE FROM contact_list WHERE contact_id = ? AND list_id IN ($in)", [(int)$cid]);
                    $stats['removed']++;
                }
            }
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $stats['message'] = "Synchro Microsoft 365 : {$stats['emails']} adresse(s) lue(s), {$stats['created']} nouveau(x) contact(s), "
        . "{$stats['updated']} mis à jour, {$stats['removed']} retiré(s) des listes synchronisées"
        . ($stats['skipped_invalid'] ? ", {$stats['skipped_invalid']} adresse(s) invalide(s) ignorée(s)" : '') . '.';
    save_setting('m365_last_sync', now());
    save_setting('m365_last_result', $stats['message']);
    log_activity($stats['message']);
    return $stats;
}

/** Appelé par le cron : synchro automatique si l'intervalle est écoulé. */
function m365_maybe_autosync(): ?string
{
    $hours = (int)setting('m365_auto_hours');
    if ($hours <= 0 || !m365_configured()) {
        return null;
    }
    $last = strtotime(setting('m365_last_sync') ?: '2000-01-01');
    $lastTry = (int)setting('m365_last_try');
    if (time() - $last < $hours * 3600 || time() - $lastTry < 3600) {
        return null;
    }
    save_setting('m365_last_try', (string)time());
    try {
        return m365_sync()['message'];
    } catch (\Throwable $e) {
        $msg = 'Synchro Microsoft 365 en échec : ' . $e->getMessage();
        save_setting('m365_last_result', $msg);
        log_activity($msg, 'error');
        return $msg;
    }
}
