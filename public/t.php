<?php
declare(strict_types=1);

/** Pixel de suivi d'ouverture (approximatif : beaucoup de clients bloquent les images). */
require dirname(__DIR__) . '/lib/bootstrap.php';

$token = (string)($_GET['m'] ?? '');
if ($token !== '') {
    q('UPDATE messages SET open_count = open_count + 1, opened_at = COALESCE(opened_at, ?) WHERE token = ?', [now(), $token]);
}
header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
