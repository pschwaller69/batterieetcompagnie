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

/** Réglages de la synchronisation Microsoft 365 (page dédiée). */
const M365_SETTING_DEFAULTS = [
    'm365_tenant' => '',                 // ID d'annuaire (tenant) ou domaine
    'm365_client_id' => '',              // ID d'application (client)
    'm365_client_secret' => '',          // chiffré
    'm365_mailboxes' => 'info@batterieetcompagnie.fr',
    'm365_folders' => '',                // vide = tous les dossiers de contacts
    'm365_list_name' => 'Microsoft 365',
    'm365_categories_as_lists' => '1',
    'm365_remove_missing' => '1',
    'm365_auto_hours' => '24',           // 0 = manuel uniquement
];

const SECRET_SETTINGS = ['smtp_pass', 'm365_client_secret'];

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
    $v = $cache[$key] ?? SETTING_DEFAULTS[$key] ?? M365_SETTING_DEFAULTS[$key] ?? '';
    if (in_array($key, SECRET_SETTINGS, true)) {
        return decrypt_value($v);
    }
    return $v;
}

function save_setting(string $key, string $value): void
{
    if (in_array($key, SECRET_SETTINGS, true)) {
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
