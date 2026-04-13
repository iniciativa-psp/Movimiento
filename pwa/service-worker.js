// PSP Service Worker v1.0
const CACHE = 'psp-v1';
const OFFLINE_URL = '/offline/';
const CACHED = [
  '/', '/registro/', '/mi-cuenta/', '/ranking/', '/dashboard/',
  '/wp-content/plugins/psp-core/assets/psp-global.css',
];

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE).then(c => c.addAll(CACHED)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  if (e.request.url.includes('admin-ajax.php')) return;

  e.respondWith(
    caches.match(e.request).then(cached => {
      const network = fetch(e.request).then(res => {
        if (res.ok) {
          const clone = res.clone();
          caches.open(CACHE).then(c => c.put(e.request, clone));
        }
        return res;
      }).catch(() => caches.match(OFFLINE_URL));
      return cached || network;
    })
  );
});

// Push notifications
self.addEventListener('push', e => {
  const data = e.data?.json() || {};
  e.waitUntil(
    self.registration.showNotification(data.title || 'Panamá Sin Pobreza', {
      body:    data.body || '¡Nuevo mensaje del Movimiento!',
      icon:    '/wp-content/plugins/psp-core/assets/icons/icon-192.png',
      badge:   '/wp-content/plugins/psp-core/assets/icons/badge-72.png',
      data:    data,
      actions: [{ action: 'open', title: 'Ver' }]
    })
  );
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  e.waitUntil(clients.openWindow(e.notification.data?.url || '/'));
});
