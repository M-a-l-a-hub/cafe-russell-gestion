/* Service worker Café Russell.
 * - /api/* : jamais mis en cache (données financières, session, CSRF).
 * - Fichiers statiques : réseau d'abord, cache en secours (l'app démarre
 *   hors connexion, mais les actions métier exigent le serveur).
 * Incrémenter VERSION pour forcer le renouvellement du cache.
 */
const VERSION = 'cafe-russell-v8';

const COQUILLE = [
  '/',
  '/assets/app.css',
  '/assets/app.js',
  '/assets/hors-ligne.js',
  '/manifest.json',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
  '/icons/icon.svg',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(VERSION)
      .then((cache) => cache.addAll(COQUILLE))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((noms) => Promise.all(
        noms.filter((n) => n !== VERSION).map((n) => caches.delete(n))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  if (url.origin === self.location.origin && url.pathname.startsWith('/api/')) return;

  const memeOrigine = url.origin === self.location.origin;
  const police = url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com';
  if (!memeOrigine && !police) return;

  event.respondWith(
    fetch(req)
      .then((reponse) => {
        if (reponse.ok) {
          const copie = reponse.clone();
          caches.open(VERSION).then((cache) => cache.put(req, copie));
        }
        return reponse;
      })
      .catch(() =>
        caches.match(req).then((enCache) => {
          if (enCache) return enCache;
          if (req.mode === 'navigate') return caches.match('/');
          return Response.error();
        })
      )
  );
});
