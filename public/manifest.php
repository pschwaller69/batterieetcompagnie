<?php
declare(strict_types=1);

/** Manifeste de l'application web installable (PWA). */
require dirname(__DIR__) . '/lib/bootstrap.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$color = setting('brand_color') ?: '#b3261e';
echo json_encode([
    'id' => './',
    'name' => 'Mailing ' . (setting('org_name') ?: 'Batterie & Compagnie'),
    'short_name' => 'Mailing B&C',
    'description' => "Outil de mailing de l'association",
    'lang' => 'fr',
    'start_url' => './index.php?p=dashboard&source=pwa',
    'scope' => './',
    'display' => 'standalone',
    'orientation' => 'any',
    'background_color' => '#f6f5f3',
    'theme_color' => $color,
    'icons' => [
        ['src' => 'assets/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => 'assets/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => 'assets/icons/maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
    'shortcuts' => [
        ['name' => 'Nouvelle campagne', 'url' => './index.php?p=campaign_edit', 'icons' => [['src' => 'assets/icons/icon-192.png', 'sizes' => '192x192']]],
        ['name' => 'Campagnes', 'url' => './index.php?p=campaigns'],
        ['name' => 'Contacts', 'url' => './index.php?p=contacts'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
