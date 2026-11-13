<?php
declare(strict_types=1);

/**
 * Déclenchement de la file par URL (alternative au cron CLI, ex. service de cron externe).
 * Actif uniquement si 'cron_url_key' est renseigné dans config.php.
 *   https://mailing.exemple.fr/cron.php?key=VOTRE_CLE
 */
require dirname(__DIR__) . '/lib/bootstrap.php';

$key = (string)config('cron_url_key', '');
if ($key === '' || !hash_equals($key, (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Interdit');
}
ignore_user_abort(true);
set_time_limit(300);
save_setting('last_cron_run', now());
$r = process_queue();
header('Content-Type: text/plain; charset=utf-8');
echo $r['message'];
