<?php
declare(strict_types=1);

const SETTING_DEFAULTS = [
    'org_name' => 'Batterie & Compagnie',
    'org_address' => '',
    'base_url' => '',                    // ex. https://mailing.batterieetcompagnie.fr
    'smtp_host' => '',                   // ex. xxxxx.o2switch.net
    'smtp_port' => '465',
    'smtp_secure' => 'ssl',              // ssl (465) | tls (587) | none
    'smtp_user' => 'info@m.batterieetcompagnie.fr',
    'smtp_pass' => '',                   // chiffré
    'from_email' => 'info@m.batterieetcompagnie.fr',
    'from_name' => 'Batterie & Compagnie',
    'reply_to' => 'info@batterieetcompagnie.fr',
    'hourly_limit' => '100',             // plafond d'envois par heure glissante
    'per_run' => '25',                   // nombre max d'envois par passage du cron
    'delay_ms' => '1500',                // pause entre deux envois
    'brand_color' => '#b3261e',
    'logo_url' => '',
    'footer_html' => '',
];

function setting(string $key): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (q('SELECT key, value FROM settings')->fetchAll() as $r) {
            $cache[$r['key']] = (string)$r['value'];
        }
    }
    if ($key === '__reset') {
        $cache = null;
        return '';
    }
    $v = $cache[$key] ?? SETTING_DEFAULTS[$key] ?? '';
    if ($key === 'smtp_pass') {
        return decrypt_value($v);
    }
    return $v;
}

function save_setting(string $key, string $value): void
{
    if ($key === 'smtp_pass') {
        $value = encrypt_value($value);
    }
    q('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value', [$key, $value]);
    setting('__reset');
}

function base_url(): string
{
    $b = rtrim(setting('base_url'), '/');
    if ($b !== '') {
        return $b;
    }
    // Déduction depuis la requête courante (inutilisable en CLI)
    if (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        return $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir;
    }
    return '';
}
