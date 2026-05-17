const CACHE_NAME = 'hivenova-static-v1';
const CACHE_URLS = [
  './styles/resource/css/tokens.css',
  './styles/resource/css/ingame/main.css',
  './scripts/game/base.js',
  './favicon.ico'
];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function (cache) {
      return cache.addAll(CACHE_URLS).catch(function () {});
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', function (event) {
  if (event.request.method !== 'GET') {
    return;
  }
  var url = new URL(event.request.url);
  if (url.pathname.indexOf('/styles/') === -1 && url.pathname.indexOf('/scripts/') === -1) {
    return;
  }
  event.respondWith(
    caches.match(event.request).then(function (cached) {
      return cached || fetch(event.request).then(function (response) {
        if (response && response.status === 200) {
          var copy = response.clone();
          caches.open(CACHE_NAME).then(function (cache) {
            cache.put(event.request, copy);
          });
        }
        return response;
      });
    })
  );
});

self.addEventListener('push', function (event) {
  var payload = { title: 'HiveNova', body: '', url: 'game.php?page=overview' };
  try {
    if (event.data) {
      payload = Object.assign(payload, event.data.json());
    }
  } catch (e) {}
  event.waitUntil(
    self.registration.showNotification(payload.title, {
      body: payload.body,
      icon: 'favicon.ico',
      data: { url: payload.url || 'game.php?page=overview' }
    })
  );
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  var target = (event.notification.data && event.notification.data.url) || 'game.php?page=overview';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
      for (var i = 0; i < clientList.length; i++) {
        if ('focus' in clientList[i]) {
          clientList[i].navigate(target);
          return clientList[i].focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(target);
      }
    })
  );
});
