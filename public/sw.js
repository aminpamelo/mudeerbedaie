const CACHE_NAME = 'mudeer-hr-v3';
const STATIC_ASSETS = [
    '/hr/clock',
    '/manifest.json',
];

// Install - cache static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        })
    );
    self.skipWaiting();
});

// Activate - clean old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            );
        })
    );
    self.clients.claim();
});

// Fetch - network first, fallback to cache
self.addEventListener('fetch', (event) => {
    // Skip non-GET requests
    if (event.request.method !== 'GET') {
        return;
    }

    // For API calls - network only
    if (event.request.url.includes('/api/')) {
        event.respondWith(fetch(event.request));
        return;
    }

    // For page navigations - network first, cache fallback
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request)
                .then((response) => {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                    return response;
                })
                .catch(() => caches.match(event.request))
        );
        return;
    }

    // For static assets - network first, cache fallback (prevents stale builds)
    event.respondWith(
        fetch(event.request)
            .then((response) => {
                const clone = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                return response;
            })
            .catch(() => caches.match(event.request))
    );
});

// Background Sync for offline clock-in/out
self.addEventListener('sync', (event) => {
    if (event.tag === 'clock-sync') {
        event.waitUntil(syncClockData());
    }
});

async function syncClockData() {
    try {
        const db = await openDB();
        const tx = db.transaction('pending-clocks', 'readonly');
        const store = tx.objectStore('pending-clocks');
        const items = await getAllFromStore(store);

        for (const item of items) {
            try {
                const response = await fetch(item.url, {
                    method: 'POST',
                    body: item.formData,
                    credentials: 'same-origin',
                });
                if (response.ok) {
                    const deleteTx = db.transaction('pending-clocks', 'readwrite');
                    deleteTx.objectStore('pending-clocks').delete(item.id);
                }
            } catch (e) {
                // Will retry on next sync
            }
        }
    } catch (e) {
        // IndexedDB not available
    }
}

function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('mudeer-hr', 1);
        request.onupgradeneeded = () => {
            request.result.createObjectStore('pending-clocks', { keyPath: 'id', autoIncrement: true });
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function getAllFromStore(store) {
    return new Promise((resolve, reject) => {
        const request = store.getAll();
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

// Push notification handler
self.addEventListener('push', (event) => {
    let payload = { title: 'Mudeer HR', body: 'New notification' };
    try {
        payload = event.data?.json() || payload;
    } catch (e) {
        // If JSON parse fails, try text
        const text = event.data?.text() || '';
        payload = { title: 'Mudeer HR', body: text };
    }

    // WebPushMessage sends: { title, body, icon, badge, data: { url } }
    const title = payload.title || 'Mudeer HR';
    const options = {
        body: payload.body || 'New notification',
        icon: payload.icon || '/icons/hr-192.svg',
        badge: payload.badge || '/icons/hr-192.svg',
        data: {
            url: (payload.data && payload.data.url) || '/hr',
        },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/hr';
    event.waitUntil(clients.openWindow(url));
});
