/**
 * Service worker for the CEO Overview PWA (scope: /ceo).
 *
 * The CEO dashboard is read-only, so this worker is intentionally lean — no push
 * or background sync (unlike the HR worker). It keeps the installed app usable
 * offline by serving the last-seen /ceo pages and build assets from cache when
 * the network is unavailable, and stays fresh with a network-first strategy when
 * it is.
 */
const CACHE = 'mudeer-ceo-v1';
const OFFLINE_FALLBACK = '/ceo';

self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys.filter((k) => k.startsWith('mudeer-ceo-') && k !== CACHE).map((k) => caches.delete(k))
        )
      )
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;

  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);

  if (url.origin !== self.location.origin) {
    return;
  }

  // Page navigations — network-first, fall back to the cached page (or the
  // /ceo shell) when offline.
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          const copy = response.clone();
          caches.open(CACHE).then((cache) => cache.put(request, copy));
          return response;
        })
        .catch(() => caches.match(request).then((cached) => cached || caches.match(OFFLINE_FALLBACK)))
    );
    return;
  }

  // Built assets + icons — stale-while-revalidate so the shell renders instantly
  // and quietly updates in the background.
  if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
    event.respondWith(
      caches.match(request).then((cached) => {
        const network = fetch(request)
          .then((response) => {
            const copy = response.clone();
            caches.open(CACHE).then((cache) => cache.put(request, copy));
            return response;
          })
          .catch(() => cached);
        return cached || network;
      })
    );
  }
});
