/**
 * Service worker for the Live Host Pocket PWA (scope: /live-host).
 *
 * Two jobs:
 *   1. Offline shell — keep the installed app usable when the network drops by
 *      serving the last-seen /live-host pages and build assets from cache
 *      (network-first for navigations, stale-while-revalidate for assets). This
 *      mirrors the lean CEO worker.
 *   2. Web push — receive `push` events and surface them as notifications, and
 *      route taps back into the app via `notificationclick`. This mirrors the HR
 *      worker. The payload shape is whatever WebPushMessage sends:
 *      { title, body, icon, badge, data: { url } }.
 */
const CACHE = 'mudeer-pocket-v1';
const OFFLINE_FALLBACK = '/live-host';
const DEFAULT_ICON = '/icons/pocket-192.svg';

self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys.filter((k) => k.startsWith('mudeer-pocket-') && k !== CACHE).map((k) => caches.delete(k))
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
  // /live-host shell) when offline.
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

// Web push — render the notification sent by the server (Laravel WebPushChannel).
self.addEventListener('push', (event) => {
  let payload = { title: 'Hos Siaran Langsung', body: 'Notifikasi baharu' };
  try {
    payload = event.data?.json() || payload;
  } catch (e) {
    const text = event.data?.text() || '';
    payload = { title: 'Hos Siaran Langsung', body: text };
  }

  const title = payload.title || 'Hos Siaran Langsung';
  const options = {
    body: payload.body || 'Notifikasi baharu',
    icon: payload.icon || DEFAULT_ICON,
    badge: payload.badge || DEFAULT_ICON,
    lang: 'ms',
    vibrate: [80, 40, 80],
    data: {
      url: (payload.data && payload.data.url) || OFFLINE_FALLBACK,
    },
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

// Tapping a notification focuses an existing pocket window if one is open,
// otherwise opens a new one at the notification's target URL.
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = event.notification.data?.url || OFFLINE_FALLBACK;

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if (client.url.includes('/live-host') && 'focus' in client) {
          client.navigate(target);
          return client.focus();
        }
      }
      return self.clients.openWindow(target);
    })
  );
});
