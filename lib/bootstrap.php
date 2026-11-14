<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_NAME', 'Mailing Batterie & Compagnie');

$configFile = APP_ROOT . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit("config.php manquant : copiez config.sample.php en config.php et renseignez app_secret.\n");
}
$GLOBALS['CONFIG'] = require $configFile;
if (($GLOBALS['CONFIG']['app_secret'] ?? '') === '' || $GLOBALS['CONFIG']['app_secret'] === 'A_REMPLACER') {
    http_response_code(500);
    exit("config.php : app_secret n'est pas défini.\n");
}
date_default_timezone_set($GLOBALS['CONFIG']['timezone'] ?? 'Europe/Paris');

require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/settings.php';
require __DIR__ . '/mailer.php';
require __DIR__ . '/queue.php';
require __DIR__ . '/importer.php';
require __DIR__ . '/m365.php';

function config(string $key, $default = null)
{
    return $GLOBALS['CONFIG'][$key] ?? $default;
}
