/* PSP Service Worker v1.0.2 */
var CACHE   = 'psp-v1';
var OFFLINE = '/offline/';
var PRE_CACHE = ['/', '/registro/', '/apoyar/', '/mi-cuenta/', '/ranking/'];

self.addEventListener('install', function(e) {
  e.waitUntil(
    caches.open(CACHE).then(function(c) {
      return c.addAll(PRE_CACHE.map(function(url){
        return new Request(url, {credentials:'same-origin'});
      })).catch(function(){});
    }).then(function(){ return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function(e) {
  e.waitUntil(
    caches.keys().then(function(keys) {
      return Promise.all(keys.filter(function(k){ return k!==CACHE; }).map(function(k){ return caches.delete(k); }));
    }).then(function(){ return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function(e) {
  if (e.request.method !== 'GET') return;
  if (e.request.url.indexOf('admin-ajax.php') > -1) return;
  if (e.request.url.indexOf('/wp-admin') > -1) return;
  if (e.request.url.indexOf('supabase.co') > -1) return;

  e.respondWith(
    caches.match(e.request).then(function(cached) {
      var network = fetch(e.request).then(function(res) {
        if (res.ok && res.type === 'basic') {
          var clone = res.clone();
          caches.open(CACHE).then(function(c){ c.put(e.request, clone); });
        }
        return res;
      }).catch(function() {
        return caches.match(OFFLINE) || new Response('<h1>Sin conexión</h1>', {headers:{'Content-Type':'text/html'}});
      });
      return cached || network;
    })
  );
});

self.addEventListener('push', function(e) {
  if (!e.data) return;
  var data = {};
  try { data = e.data.json(); } catch(err) { data = {title:'PSP', body: e.data.text()}; }
  e.waitUntil(
    self.registration.showNotification(data.title || 'Panamá Sin Pobreza', {
      body   : data.body  || '¡Nuevo mensaje del Movimiento!',
      icon   : data.icon  || '/wp-content/plugins/psp-pwa/assets/icon-192.png',
      badge  : '/wp-content/plugins/psp-pwa/assets/icon-192.png',
      data   : data,
      actions: [{ action:'open', title:'Ver' }]
    })
  );
});

self.addEventListener('notificationclick', function(e) {
  e.notification.close();
  var url = (e.notification.data && e.notification.data.url) ? e.notification.data.url : '/';
  e.waitUntil(clients.openWindow(url));
});
