<?php
declare(strict_types=1);

/**
 * Traitement de la file d'envoi — à lancer toutes les 5 minutes par le cron de cPanel :
 *   /usr/local/bin/php /home/VOTRE_COMPTE/mailing/cron.php >/dev/null 2>&1
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require __DIR__ . '/lib/bootstrap.php';

save_setting('last_cron_run', now());
$r = process_queue();
echo '[' . now() . '] ' . $r['message'] . PHP_EOL;
