const CACHE_NAME = 'madrasah-terpadu-static-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE_NAME)
            .then((cache) => cache.add(OFFLINE_URL))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== CACHE_NAME)
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Jangan pernah cache halaman berisi data atau respons Inertia/JSON.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL)),
        );
        return;
    }

    if (
        url.origin === self.location.origin &&
        url.pathname.startsWith('/build/assets/')
    ) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ||
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches
                                .open(CACHE_NAME)
                                .then((cache) => cache.put(request, copy));
                        }
                        return response;
                    }),
            ),
        );
    }
});

self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    let payload = {};
    try {
        payload = event.data.json();
    } catch {
        payload = { title: 'Madrasah Terpadu', body: event.data.text() };
    }

    const title = payload.title || 'Madrasah Terpadu';
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/apple-touch-icon.png',
        badge: payload.badge || '/apple-touch-icon.png',
        tag: payload.tag || undefined,
        data: { url: payload.url || '/wali' },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = event.notification.data?.url || '/wali';

    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((clients) => {
                for (const client of clients) {
                    if ('focus' in client && client.url.includes(targetUrl)) {
                        return client.focus();
                    }
                }
                return self.clients.openWindow(targetUrl);
            }),
    );
});
