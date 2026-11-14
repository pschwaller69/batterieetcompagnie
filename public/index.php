<?php
declare(strict_types=1);

require dirname(__DIR__) . '/lib/bootstrap.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

start_session();
$page = preg_replace('/[^a-z0-9_]/', '', (string)($_GET['p'] ?? 'dashboard')) ?: 'dashboard';
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
if ($isPost) {
    check_csrf();
}

/* ---------- Premier lancement ---------- */
$hasUsers = (int)q_val('SELECT COUNT(*) FROM users') > 0;
if (!$hasUsers && $page !== 'setup') {
    redirect('setup');
}

switch ($page) {

/* ===================== Authentification ===================== */

case 'setup':
    if ($hasUsers) {
        redirect('login');
    }
    $error = '';
    if ($isPost) {
        $email = normalize_email(post('email'));
        $pass = (string)($_POST['password'] ?? '');
        if (!valid_email($email)) {
            $error = 'Adresse e-mail invalide.';
        } elseif (strlen($pass) < 10) {
            $error = 'Mot de passe : 10 caractères minimum.';
        } else {
            q('INSERT INTO users (email, name, password_hash) VALUES (?, ?, ?)', [$email, post('name'), password_hash($pass, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)db()->lastInsertId();
            if (setting('base_url') === '') {
                save_setting('base_url', base_url());
            }
            flash('Compte administrateur créé. Configurez maintenant l\'envoi (SMTP).');
            redirect('settings');
        }
    }
    render('setup', ['error' => $error], false);
    break;

case 'login':
    if (current_user()) {
        redirect('dashboard');
    }
    $error = '';
    if ($isPost) {
        $u = q_one('SELECT * FROM users WHERE email = ?', [normalize_email(post('email'))]);
        if ($u && password_verify((string)($_POST['password'] ?? ''), $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['id'];
            redirect('dashboard');
        }
        sleep(2);
        $error = 'Identifiants incorrects.';
    }
    render('login', ['error' => $error], false);
    break;

case 'logout':
    $_SESSION = [];
    session_destroy();
    redirect('login');
    break;

/* ===================== Tableau de bord ===================== */

case 'dashboard':
    $user = require_login();
    if ($isPost && post('action') === 'process') {
        $r = process_queue();
        flash($r['message'], $r['failed'] ? 'warn' : 'ok');
        redirect('dashboard');
    }
    render('dashboard', [
        'user' => $user,
        'counts' => q_one("SELECT COUNT(*) AS total, SUM(status='subscribed') AS subscribed, SUM(status='unsubscribed') AS unsubscribed, SUM(status='bounced') AS bounced FROM contacts"),
        'lists' => (int)q_val('SELECT COUNT(*) FROM lists'),
        'pending' => (int)q_val("SELECT COUNT(*) FROM messages m JOIN campaigns c ON c.id=m.campaign_id WHERE m.status='pending' AND c.status IN ('sending','scheduled')"),
        'sentHour' => sent_last_hour(),
        'recent' => q('SELECT * FROM campaigns ORDER BY id DESC LIMIT 5')->fetchAll(),
        'activity' => q('SELECT * FROM activity ORDER BY id DESC LIMIT 12')->fetchAll(),
        'lastCron' => setting('last_cron_run'),
        'smtpOk' => setting('smtp_host') !== '',
    ]);
    break;

/* ===================== Contacts ===================== */

case 'contacts':
    require_login();
    if ($isPost) {
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        $action = post('bulk');
        $lid = (int)post('list_id');
        if (!$ids) {
            flash('Aucun contact sélectionné.', 'warn');
        } else {
            $in = implode(',', $ids);
            switch ($action) {
                case 'add_list':
                    if ($lid) {
                        foreach ($ids as $id) {
                            q('INSERT OR IGNORE INTO contact_list (contact_id, list_id) VALUES (?, ?)', [$id, $lid]);
                        }
                        flash(count($ids) . ' contact(s) ajouté(s) à la liste.');
                    }
                    break;
                case 'remove_list':
                    if ($lid) {
                        q("DELETE FROM contact_list WHERE list_id = ? AND contact_id IN ($in)", [$lid]);
                        flash(count($ids) . ' contact(s) retiré(s) de la liste.');
                    }
                    break;
                case 'unsubscribe':
                    q("UPDATE contacts SET status='unsubscribed', unsubscribed_at=?, updated_at=? WHERE id IN ($in)", [now(), now()]);
                    flash(count($ids) . ' contact(s) désinscrit(s).');
                    break;
                case 'resubscribe':
                    q("UPDATE contacts SET status='subscribed', unsubscribed_at=NULL, updated_at=? WHERE id IN ($in)", [now()]);
                    flash(count($ids) . ' contact(s) réabonné(s). Assurez-vous d\'avoir leur accord.', 'warn');
                    break;
                case 'delete':
                    q("DELETE FROM contacts WHERE id IN ($in)");
                    flash(count($ids) . ' contact(s) supprimé(s).');
                    break;
            }
        }
        redirect('contacts', array_filter(['q' => get('q'), 'list' => get('list'), 'status' => get('status')]));
    }
    $where = [];
    $params = [];
    if (($s = get('q')) !== '') {
        $where[] = '(c.email LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ?)';
        array_push($params, "%$s%", "%$s%", "%$s%");
    }
    if (($l = (int)get('list')) > 0) {
        $where[] = 'c.id IN (SELECT contact_id FROM contact_list WHERE list_id = ?)';
        $params[] = $l;
    }
    if (in_array($st = get('status'), ['subscribed', 'unsubscribed', 'bounced'], true)) {
        $where[] = 'c.status = ?';
        $params[] = $st;
    }
    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $total = (int)q_val("SELECT COUNT(*) FROM contacts c $w", $params);
    $per = 50;
    $pg = max(1, (int)get('page', 1));
    $rows = q("SELECT c.*, (SELECT GROUP_CONCAT(l.name, ', ') FROM contact_list cl JOIN lists l ON l.id = cl.list_id WHERE cl.contact_id = c.id) AS list_names
               FROM contacts c $w ORDER BY c.last_name COLLATE NOCASE, c.first_name COLLATE NOCASE, c.email LIMIT $per OFFSET " . (($pg - 1) * $per), $params)->fetchAll();
    render('contacts', [
        'rows' => $rows, 'total' => $total, 'page' => $pg, 'pages' => (int)ceil($total / $per),
        'lists' => q('SELECT * FROM lists ORDER BY name COLLATE NOCASE')->fetchAll(),
    ]);
    break;

case 'contact':
    require_login();
    $id = (int)get('id');
    $c = $id ? q_one('SELECT * FROM contacts WHERE id = ?', [$id]) : null;
    if ($id && !$c) {
        redirect('contacts');
    }
    $error = '';
    if ($isPost) {
        $email = normalize_email(post('email'));
        $status = in_array(post('status'), ['subscribed', 'unsubscribed', 'bounced'], true) ? post('status') : 'subscribed';
        $dup = q_val('SELECT id FROM contacts WHERE email = ? AND id <> ?', [$email, $id]);
        if (!valid_email($email)) {
            $error = 'Adresse e-mail invalide.';
        } elseif ($dup) {
            $error = 'Un autre contact utilise déjà cette adresse.';
        } else {
            if ($c) {
                q('UPDATE contacts SET email=?, first_name=?, last_name=?, phone=?, notes=?, status=?, updated_at=?,
                   unsubscribed_at = CASE WHEN ? = \'unsubscribed\' THEN COALESCE(unsubscribed_at, ?) ELSE NULL END WHERE id=?',
                    [$email, post('first_name'), post('last_name'), post('phone'), post('notes'), $status, now(), $status, now(), $id]);
            } else {
                q('INSERT INTO contacts (email, first_name, last_name, phone, notes, status, token) VALUES (?,?,?,?,?,?,?)',
                    [$email, post('first_name'), post('last_name'), post('phone'), post('notes'), $status, random_token(18)]);
                $id = (int)db()->lastInsertId();
            }
            q('DELETE FROM contact_list WHERE contact_id = ?', [$id]);
            foreach ((array)($_POST['lists'] ?? []) as $lid) {
                q('INSERT OR IGNORE INTO contact_list (contact_id, list_id) VALUES (?, ?)', [$id, (int)$lid]);
            }
            flash('Contact enregistré.');
            redirect('contacts');
        }
        $c = array_merge($c ?? [], $_POST);
    }
    render('contact', [
        'c' => $c, 'error' => $error,
        'lists' => q('SELECT * FROM lists ORDER BY name COLLATE NOCASE')->fetchAll(),
        'mine' => $id ? array_column(q('SELECT list_id FROM contact_list WHERE contact_id = ?', [$id])->fetchAll(), 'list_id') : array_map('intval', (array)($_POST['lists'] ?? [])),
        'history' => $id ? q('SELECT m.*, k.name FROM messages m JOIN campaigns k ON k.id = m.campaign_id WHERE m.contact_id = ? ORDER BY m.id DESC LIMIT 20', [$id])->fetchAll() : [],
    ]);
    break;

case 'export':
    require_login();
    $l = (int)get('list');
    $sql = 'SELECT c.email, c.first_name, c.last_name, c.phone, c.status, c.created_at,
            (SELECT GROUP_CONCAT(l.name, \' | \') FROM contact_list cl JOIN lists l ON l.id = cl.list_id WHERE cl.contact_id = c.id) AS listes
            FROM contacts c' . ($l ? ' WHERE c.id IN (SELECT contact_id FROM contact_list WHERE list_id = ' . $l . ')' : '') . ' ORDER BY c.email';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="contacts-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Email', 'Prénom', 'Nom', 'Téléphone', 'Statut', 'Créé le', 'Listes'], ';', '"', '');
    foreach (q($sql)->fetchAll() as $r) {
        $r['status'] = status_label($r['status']);
        fputcsv($out, array_values($r), ';', '"', '');
    }
    exit;

case 'import':
    require_login();
    $tmpDir = dirname(config('db_path', APP_ROOT . '/data/x')) . '/tmp';
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0750, true);
    }
    $lists = q('SELECT * FROM lists ORDER BY name COLLATE NOCASE')->fetchAll();
    $step = 1;
    $data = [];
    if ($isPost && post('step') === '1') {
        try {
            $f = $_FILES['file'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Aucun fichier reçu.');
            }
            $rows = read_table_file($f['tmp_name'], $f['name']);
            if (count($rows) < 1) {
                throw new RuntimeException('Le fichier est vide.');
            }
            $key = random_token(12);
            file_put_contents("$tmpDir/$key.json", json_encode($rows));
            $_SESSION['import'] = ['key' => $key, 'name' => $f['name']];
            $step = 2;
        } catch (\Throwable $e) {
            flash($e->getMessage(), 'err');
            redirect('import');
        }
    } elseif ($isPost && post('step') === '2') {
        $imp = $_SESSION['import'] ?? null;
        $file = $imp ? "$tmpDir/{$imp['key']}.json" : '';
        if (!$imp || !is_file($file)) {
            flash('Import expiré, recommencez.', 'err');
            redirect('import');
        }
        $rows = json_decode(file_get_contents($file), true);
        $hasHeader = post('has_header') === '1';
        if ($hasHeader) {
            array_shift($rows);
        }
        $map = [];
        foreach (['email', 'first_name', 'last_name', 'phone'] as $k) {
            $v = post('map_' . $k);
            $map[$k] = $v === '' ? null : (int)$v;
        }
        if ($map['email'] === null) {
            flash('Indiquez la colonne contenant l\'e-mail.', 'err');
            redirect('import');
        }
        $listIds = array_map('intval', (array)($_POST['lists'] ?? []));
        if (($newList = post('new_list')) !== '') {
            q('INSERT OR IGNORE INTO lists (name) VALUES (?)', [$newList]);
            $listIds[] = (int)q_val('SELECT id FROM lists WHERE name = ?', [$newList]);
        }
        $s = import_rows($rows, $map, $listIds, post('update') === '1');
        @unlink($file);
        unset($_SESSION['import']);
        $msg = "Import terminé : {$s['created']} créé(s), {$s['updated']} mis à jour, {$s['invalid']} adresse(s) invalide(s), {$s['duplicates']} doublon(s) dans le fichier.";
        if ($s['skipped_unsub']) {
            $msg .= " {$s['skipped_unsub']} contact(s) déjà désinscrit(s) le restent.";
        }
        log_activity($msg);
        flash($msg);
        redirect('contacts');
    }
    if ($step === 2) {
        $data = [
            'header' => $rows[0],
            'sample' => array_slice($rows, 1, 5),
            'count' => count($rows),
            'guess' => guess_mapping($rows[0]),
            'name' => $_SESSION['import']['name'],
        ];
    }
    render('import', ['step' => $step, 'data' => $data, 'lists' => $lists]);
    break;

/* ===================== Listes ===================== */

case 'lists':
    require_login();
    if ($isPost) {
        $a = post('action');
        if ($a === 'create' && post('name') !== '') {
            try {
                q('INSERT INTO lists (name, description) VALUES (?, ?)', [post('name'), post('description')]);
                flash('Liste créée.');
            } catch (PDOException $e) {
                flash('Une liste porte déjà ce nom.', 'err');
            }
        } elseif ($a === 'update') {
            q('UPDATE lists SET name = ?, description = ? WHERE id = ?', [post('name'), post('description'), (int)post('id')]);
            flash('Liste modifiée.');
        } elseif ($a === 'delete') {
            q('DELETE FROM lists WHERE id = ?', [(int)post('id')]);
            flash('Liste supprimée (les contacts sont conservés).');
        }
        redirect('lists');
    }
    render('lists', ['lists' => q("SELECT l.*,
        (SELECT COUNT(*) FROM contact_list cl JOIN contacts c ON c.id = cl.contact_id WHERE cl.list_id = l.id) AS n,
        (SELECT COUNT(*) FROM contact_list cl JOIN contacts c ON c.id = cl.contact_id WHERE cl.list_id = l.id AND c.status = 'subscribed') AS n_sub
        FROM lists l ORDER BY l.name COLLATE NOCASE")->fetchAll()]);
    break;

/* ===================== Campagnes ===================== */

case 'campaigns':
    require_login();
    $rows = q('SELECT * FROM campaigns ORDER BY id DESC')->fetchAll();
    foreach ($rows as &$r) {
        $r['stats'] = campaign_stats((int)$r['id']);
    }
    unset($r);
    render('campaigns', ['rows' => $rows]);
    break;

case 'campaign_edit':
    require_login();
    $id = (int)get('id');
    $c = $id ? q_one('SELECT * FROM campaigns WHERE id = ?', [$id]) : null;
    if ($c && $c['status'] !== 'draft') {
        flash('Seuls les brouillons sont modifiables. Dupliquez la campagne pour la réutiliser.', 'warn');
        redirect('campaign', ['id' => $id]);
    }
    if ($isPost) {
        $fields = [
            post('name') ?: 'Sans titre', post('subject'), post('preheader'), post('from_name'), post('reply_to'),
            (string)($_POST['html'] ?? ''), post('target') === 'all' ? 1 : 0, post('track_opens') === '1' ? 1 : 0,
        ];
        if ($c) {
            q('UPDATE campaigns SET name=?, subject=?, preheader=?, from_name=?, reply_to=?, html=?, target_all=?, track_opens=?, updated_at=? WHERE id=?',
                array_merge($fields, [now(), $id]));
        } else {
            q('INSERT INTO campaigns (name, subject, preheader, from_name, reply_to, html, target_all, track_opens) VALUES (?,?,?,?,?,?,?,?)', $fields);
            $id = (int)db()->lastInsertId();
        }
        q('DELETE FROM campaign_lists WHERE campaign_id = ?', [$id]);
        foreach ((array)($_POST['lists'] ?? []) as $lid) {
            q('INSERT OR IGNORE INTO campaign_lists (campaign_id, list_id) VALUES (?, ?)', [$id, (int)$lid]);
        }
        flash('Campagne enregistrée.');
        redirect(post('next') === 'view' ? 'campaign' : 'campaign_edit', ['id' => $id]);
    }
    render('campaign_edit', [
        'c' => $c ?? ['name' => '', 'subject' => '', 'preheader' => '', 'from_name' => setting('from_name'), 'reply_to' => setting('reply_to'), 'html' => '', 'target_all' => 0, 'track_opens' => 1],
        'id' => $id,
        'lists' => q("SELECT l.*, (SELECT COUNT(*) FROM contact_list cl JOIN contacts c ON c.id = cl.contact_id WHERE cl.list_id = l.id AND c.status='subscribed') AS n FROM lists l ORDER BY name COLLATE NOCASE")->fetchAll(),
        'mine' => $id ? array_column(q('SELECT list_id FROM campaign_lists WHERE campaign_id = ?', [$id])->fetchAll(), 'list_id') : [],
    ]);
    break;

case 'campaign':
    $user = require_login();
    $id = (int)get('id');
    $c = q_one('SELECT * FROM campaigns WHERE id = ?', [$id]);
    if (!$c) {
        redirect('campaigns');
    }
    if ($isPost) {
        $a = post('action');
        try {
            switch ($a) {
                case 'test':
                    $to = normalize_email(post('to'));
                    if (!valid_email($to)) {
                        throw new RuntimeException('Adresse de test invalide.');
                    }
                    if (hourly_remaining() <= 0) {
                        throw new RuntimeException('Plafond horaire atteint, réessayez plus tard.');
                    }
                    send_test_email($c, $to);
                    $_SESSION['test_to'] = $to;
                    flash("Mail de test envoyé à $to.");
                    break;
                case 'send':
                case 'schedule':
                    if (trim($c['subject']) === '' || trim(strip_tags($c['html'], '<img>')) === '') {
                        throw new RuntimeException('Objet ou contenu vide.');
                    }
                    if (setting('smtp_host') === '') {
                        throw new RuntimeException('Configurez d\'abord le SMTP dans les réglages.');
                    }
                    $when = null;
                    if ($a === 'schedule') {
                        $t = strtotime(post('scheduled_at'));
                        if (!$t || $t < time()) {
                            throw new RuntimeException('Date de programmation invalide ou passée.');
                        }
                        $when = date('Y-m-d H:i:s', $t);
                    }
                    $n = enqueue_campaign($id, $when);
                    if ($n === 0) {
                        throw new RuntimeException('Aucun destinataire abonné dans les listes choisies.');
                    }
                    flash($when ? "Campagne programmée pour $n destinataire(s)." : "Envoi lancé vers $n destinataire(s). Il se fera progressivement (plafond " . setting('hourly_limit') . '/h).');
                    break;
                case 'pause':
                    q("UPDATE campaigns SET status='paused', updated_at=? WHERE id=? AND status IN ('sending','scheduled')", [now(), $id]);
                    flash('Envoi mis en pause.');
                    break;
                case 'resume':
                    q("UPDATE campaigns SET status='sending', updated_at=? WHERE id=? AND status='paused'", [now(), $id]);
                    flash('Envoi repris.');
                    break;
                case 'cancel':
                    q("UPDATE messages SET status='skipped', last_error='Campagne annulée' WHERE campaign_id=? AND status='pending'", [$id]);
                    q("UPDATE campaigns SET status='cancelled', finished_at=?, updated_at=? WHERE id=?", [now(), now(), $id]);
                    log_activity("Campagne « {$c['name']} » annulée.");
                    flash('Campagne annulée. Les messages déjà partis ne peuvent pas être rappelés.', 'warn');
                    break;
                case 'unschedule':
                    if ($c['status'] === 'scheduled') {
                        q('DELETE FROM messages WHERE campaign_id = ?', [$id]);
                        q("UPDATE campaigns SET status='draft', scheduled_at=NULL, queued_at=NULL, updated_at=? WHERE id=?", [now(), $id]);
                        flash('Programmation annulée, la campagne est revenue en brouillon.');
                    }
                    break;
                case 'retry':
                    $n = q("UPDATE messages SET status='pending', attempts=0 WHERE campaign_id=? AND status='failed'
                            AND contact_id IN (SELECT id FROM contacts WHERE status='subscribed')", [$id])->rowCount();
                    if ($n) {
                        q("UPDATE campaigns SET status='sending', finished_at=NULL, updated_at=? WHERE id=?", [now(), $id]);
                    }
                    flash("$n message(s) remis en file.");
                    break;
                case 'process':
                    $r = process_queue();
                    flash($r['message'], $r['failed'] ? 'warn' : 'ok');
                    break;
                case 'duplicate':
                    q("INSERT INTO campaigns (name, subject, preheader, from_name, reply_to, html, target_all, track_opens)
                       SELECT name || ' (copie)', subject, preheader, from_name, reply_to, html, target_all, track_opens FROM campaigns WHERE id = ?", [$id]);
                    $new = (int)db()->lastInsertId();
                    q('INSERT INTO campaign_lists (campaign_id, list_id) SELECT ?, list_id FROM campaign_lists WHERE campaign_id = ?', [$new, $id]);
                    flash('Campagne dupliquée.');
                    redirect('campaign_edit', ['id' => $new]);
                    break;
                case 'delete':
                    if (in_array($c['status'], ['sending', 'scheduled'], true)) {
                        throw new RuntimeException('Mettez la campagne en pause ou annulez-la avant de la supprimer.');
                    }
                    q('DELETE FROM campaigns WHERE id = ?', [$id]);
                    flash('Campagne supprimée.');
                    redirect('campaigns');
                    break;
            }
        } catch (\Throwable $e) {
            flash($e->getMessage(), 'err');
        }
        redirect('campaign', ['id' => $id]);
    }
    $filter = get('f');
    $mw = in_array($filter, ['pending', 'sent', 'failed', 'skipped'], true) ? ' AND status = ?' : ($filter === 'opened' ? ' AND opened_at IS NOT NULL' : '');
    $mp = in_array($filter, ['pending', 'sent', 'failed', 'skipped'], true) ? [$id, $filter] : [$id];
    render('campaign', [
        'c' => $c,
        'stats' => campaign_stats($id),
        'recipients' => $c['status'] === 'draft' ? count_recipients($c) : null,
        'listNames' => array_column(q('SELECT l.name FROM campaign_lists k JOIN lists l ON l.id = k.list_id WHERE k.campaign_id = ?', [$id])->fetchAll(), 'name'),
        'messages' => q("SELECT * FROM messages WHERE campaign_id = ? $mw ORDER BY (status='failed') DESC, id LIMIT 300", $mp)->fetchAll(),
        'filter' => $filter,
        'testTo' => $_SESSION['test_to'] ?? $user['email'],
        'remaining' => hourly_remaining(),
    ]);
    break;

case 'preview':
    require_login();
    $c = q_one('SELECT * FROM campaigns WHERE id = ?', [(int)get('id')]);
    if (!$c) {
        exit;
    }
    $sample = q_one("SELECT * FROM contacts WHERE status='subscribed' ORDER BY id LIMIT 1") ?? ['email' => 'exemple@exemple.fr', 'first_name' => 'Camille', 'last_name' => 'Exemple', 'token' => 'apercu'];
    $built = build_email(array_merge($c, ['track_opens' => 0]), $sample, 'preview');
    header('Content-Type: text/html; charset=utf-8');
    echo $built['html'];
    exit;

case 'upload_image':
    require_login();
    header('Content-Type: application/json');
    if (!$isPost || empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'Envoi impossible']);
        exit;
    }
    $f = $_FILES['image'];
    $info = @getimagesize($f['tmp_name']);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
    if (!$ext || $f['size'] > 5 * 1024 * 1024) {
        echo json_encode(['error' => 'Image JPG/PNG/GIF/WEBP de 5 Mo maximum.']);
        exit;
    }
    $dir = __DIR__ . '/uploads/' . date('Y-m');
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    move_uploaded_file($f['tmp_name'], "$dir/$name");
    echo json_encode(['url' => base_url() . '/uploads/' . date('Y-m') . '/' . $name]);
    exit;

/* ===================== Réglages / utilisateurs ===================== */

case 'settings':
    $user = require_login();
    if ($isPost) {
        $a = post('action');
        if ($a === 'save') {
            foreach (array_keys(SETTING_DEFAULTS) as $k) {
                if ($k === 'smtp_pass') {
                    if (post('smtp_pass') !== '') {
                        save_setting('smtp_pass', (string)$_POST['smtp_pass']);
                    }
                    continue;
                }
                if (array_key_exists($k, $_POST)) {
                    $v = $k === 'footer_html' ? (string)$_POST[$k] : post($k);
                    if (in_array($k, ['hourly_limit', 'per_run', 'delay_ms', 'smtp_port'], true)) {
                        $v = (string)max(0, (int)$v);
                    }
                    if ($k === 'base_url') {
                        $v = rtrim($v, '/');
                    }
                    save_setting($k, $v);
                }
            }
            flash('Réglages enregistrés.');
        } elseif ($a === 'smtp_test') {
            try {
                $to = normalize_email(post('to')) ?: $user['email'];
                send_test_email([
                    'id' => 0, 'subject' => 'Test de configuration SMTP', 'preheader' => '', 'from_name' => '', 'reply_to' => '',
                    'html' => '<p>Bonjour {{prenom}},</p><p>Si vous lisez ce message, l\'envoi via <strong>' . h(setting('smtp_host')) . '</strong> fonctionne.</p>',
                    'track_opens' => 0,
                ], $to);
                flash("Mail de test envoyé à $to. Vérifiez la réception (et le dossier spam).");
            } catch (\Throwable $e) {
                flash('Échec SMTP : ' . $e->getMessage(), 'err');
            }
        }
        redirect('settings');
    }
    render('settings', ['user' => $user, 'cronPath' => APP_ROOT . '/cron.php']);
    break;

case 'users':
    $me = require_login();
    if ($isPost) {
        $a = post('action');
        if ($a === 'create') {
            $email = normalize_email(post('email'));
            $pass = (string)($_POST['password'] ?? '');
            if (!valid_email($email) || strlen($pass) < 10) {
                flash('E-mail valide et mot de passe de 10 caractères minimum requis.', 'err');
            } else {
                try {
                    q('INSERT INTO users (email, name, password_hash) VALUES (?, ?, ?)', [$email, post('name'), password_hash($pass, PASSWORD_DEFAULT)]);
                    flash('Utilisateur ajouté.');
                } catch (PDOException $e) {
                    flash('Cet utilisateur existe déjà.', 'err');
                }
            }
        } elseif ($a === 'password') {
            $pass = (string)($_POST['password'] ?? '');
            if (strlen($pass) < 10) {
                flash('10 caractères minimum.', 'err');
            } else {
                q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), (int)post('id')]);
                flash('Mot de passe modifié.');
            }
        } elseif ($a === 'delete') {
            if ((int)post('id') === (int)$me['id']) {
                flash('Vous ne pouvez pas supprimer votre propre compte.', 'err');
            } else {
                q('DELETE FROM users WHERE id = ?', [(int)post('id')]);
                flash('Utilisateur supprimé.');
            }
        }
        redirect('users');
    }
    render('users', ['users' => q('SELECT * FROM users ORDER BY email')->fetchAll(), 'me' => $me]);
    break;

case 'm365':
    require_login();
    $probe = null;
    if ($isPost) {
        $a = post('action');
        if ($a === 'save') {
            foreach (array_keys(M365_SETTING_DEFAULTS) as $k) {
                if ($k === 'm365_client_secret') {
                    if (post($k) !== '') {
                        save_setting($k, (string)$_POST[$k]);
                        save_setting('m365_token', '');
                    }
                    continue;
                }
                if (in_array($k, ['m365_categories_as_lists', 'm365_remove_missing'], true)) {
                    save_setting($k, post($k) === '1' ? '1' : '0');
                    continue;
                }
                if (array_key_exists($k, $_POST)) {
                    $v = post($k);
                    if ($k === 'm365_auto_hours') {
                        $v = (string)max(0, (int)$v);
                    }
                    save_setting($k, $v);
                }
            }
            save_setting('m365_token', '');
            flash('Réglages Microsoft 365 enregistrés.');
            redirect('m365');
        }
        try {
            if (!m365_configured()) {
                throw new RuntimeException('Renseignez d\'abord le tenant, l\'ID d\'application, le secret et au moins une boîte.');
            }
            if ($a === 'test') {
                $probe = m365_probe();
            } elseif ($a === 'sync') {
                $r = m365_sync();
                flash($r['message']);
                redirect('m365');
            }
        } catch (\Throwable $e) {
            flash($e->getMessage(), 'err');
            if ($a === 'sync') {
                save_setting('m365_last_result', 'Échec : ' . $e->getMessage());
                redirect('m365');
            }
        }
    }
    render('m365', [
        'probe' => $probe,
        'mainList' => q_one("SELECT l.id, (SELECT COUNT(*) FROM contact_list WHERE list_id = l.id) AS n FROM lists l WHERE l.name = ?", [setting('m365_list_name')]),
        'catLists' => ($ids = array_map('intval', json_decode(setting('m365_category_lists') ?: '[]', true) ?: []))
            ? q('SELECT l.id, l.name, (SELECT COUNT(*) FROM contact_list WHERE list_id = l.id) AS n FROM lists l WHERE l.id IN (' . implode(',', $ids) . ') ORDER BY l.name')->fetchAll()
            : [],
    ]);
    break;

case 'activity':
    require_login();
    render('activity', ['rows' => q('SELECT * FROM activity ORDER BY id DESC LIMIT 300')->fetchAll()]);
    break;

default:
    redirect('dashboard');
}
