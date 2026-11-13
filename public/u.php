<?php
declare(strict_types=1);

/**
 * Désinscription.
 *  - GET  : page de confirmation (les antivirus/scanners de liens ne désinscrivent donc personne par erreur)
 *  - POST : désinscription effective, y compris le "One-Click" de Gmail/Yahoo (RFC 8058)
 */
require dirname(__DIR__) . '/lib/bootstrap.php';

$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$contact = $token !== '' ? q_one('SELECT * FROM contacts WHERE token = ?', [$token]) : null;
$org = setting('org_name');
$done = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $contact) {
    if ($contact['status'] !== 'unsubscribed') {
        q("UPDATE contacts SET status='unsubscribed', unsubscribed_at=?, updated_at=? WHERE id=?", [now(), now(), $contact['id']]);
        q("UPDATE messages SET status='skipped', last_error='Désinscrit' WHERE contact_id=? AND status='pending'", [$contact['id']]);
        log_activity('Désinscription : ' . $contact['email']);
    }
    $done = true;
    // Requête "One-Click" envoyée par le client mail : pas besoin de page
    if (isset($_POST['List-Unsubscribe'])) {
        http_response_code(200);
        exit('OK');
    }
}
$already = $contact && $contact['status'] === 'unsubscribed';
$brand = setting('brand_color') ?: '#b3261e';
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Désinscription — <?= h($org) ?></title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f4f4f4;margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center;color:#222}
.box{background:#fff;max-width:440px;width:90%;padding:32px;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,.08);text-align:center}
h1{font-size:20px;margin:0 0 12px}
button{background:<?= h($brand) ?>;color:#fff;border:0;padding:12px 22px;border-radius:6px;font-size:15px;cursor:pointer}
p{line-height:1.5}
.muted{color:#777;font-size:14px}
</style>
</head>
<body>
<div class="box">
<?php if (!$contact): ?>
    <h1>Lien invalide</h1>
    <p>Ce lien de désinscription n'est pas reconnu. Écrivez-nous en répondant à l'un de nos messages.</p>
<?php elseif ($done || $already): ?>
    <h1>C'est fait</h1>
    <p>L'adresse <strong><?= h($contact['email']) ?></strong> ne recevra plus nos envois.</p>
    <p class="muted">Une erreur ? Répondez simplement à l'un de nos messages pour être réinscrit·e.</p>
<?php else: ?>
    <h1>Se désinscrire</h1>
    <p>Ne plus recevoir les messages de <strong><?= h($org) ?></strong> à l'adresse <strong><?= h($contact['email']) ?></strong> ?</p>
    <form method="post">
        <input type="hidden" name="t" value="<?= h($token) ?>">
        <button type="submit">Confirmer la désinscription</button>
    </form>
<?php endif; ?>
</div>
</body>
</html>
