<?php
declare(strict_types=1);

/**
 * Service worker de l'application installable.
 * - Fichiers statiques (CSS, JS, icônes, éditeur) : mis en cache, rafraîchis à chaque nouvelle version.
 * - Pages : toujours chargées depuis le serveur (données à jour, rien de personnel en cache) ;
 *   page « Hors connexion » si le réseau est indisponible.
 * - Désinscription, suivi d'ouverture, cron, envois de formulaires : jamais interceptés.
 */
require dirname(__DIR__) . '/lib/version.php';

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-cache');
?>
const CACHE = 'bc-mailing-<?= APP_VERSION ?>';
const STATIC = [
  'offline.html',
  'assets/app.css?v=<?= APP_VERSION ?>',
  'assets/app.js?v=<?= APP_VERSION ?>',
  'assets/quill/quill.js',
  'assets/quill/quill.snow.css',
  'assets/icons/icon-192.png',
  'assets/icons/icon-512.png',
  'assets/icons/favicon-32.png'
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(STATIC)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k.startsWith('bc-mailing-') && k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;
  if (/\/(u|t|cron|manifest|sw)\.php$/.test(url.pathname) || url.pathname.includes('/uploads/')) return;

  // Pages : réseau d'abord, page hors connexion en secours
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('offline.html')));
    return;
  }
  // Fichiers statiques : cache d'abord
  if (url.pathname.includes('/assets/')) {
    e.respondWith(
      caches.match(req).then((hit) => hit || fetch(req).then((res) => {
        if (res.ok) {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(req, copy));
        }
        return res;
      }))
    );
  }
});
