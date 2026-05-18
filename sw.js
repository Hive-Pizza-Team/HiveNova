const CACHE_NAME = 'hivenova-static-v2';
const DEFAULT_GAME_URL = 'game.php?page=overview';

function safeGameUrl(url) {
  if (typeof url !== 'string' || url === '') {
    return DEFAULT_GAME_URL;
  }
  if (/^game\.php\?page=[a-zA-Z0-9_]+$/.test(url)) {
    return url;
  }
  return DEFAULT_GAME_URL;
}

// Precache only stable assets; main.css uses network-first (see fetch handler).
const CACHE_URLS = [
  './styles/resource/css/tokens.css',
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
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(
        keys.filter(function (key) { return key !== CACHE_NAME; }).map(function (key) {
          return caches.delete(key);
        })
      );
    }).then(function () {
      return self.clients.claim();
    })
  );
});

self.addEventListener('fetch', function (event) {
  if (event.request.method !== 'GET') {
    return;
  }
  var url = new URL(event.request.url);
  if (url.pathname.indexOf('/styles/') === -1 && url.pathname.indexOf('/scripts/') === -1) {
    return;
  }

  // Network-first so deploys reach PWA/mobile clients without stale CSS/JS.
  event.respondWith(
    fetch(event.request).then(function (response) {
      if (response && response.status === 200) {
        var copy = response.clone();
        caches.open(CACHE_NAME).then(function (cache) {
          cache.put(event.request, copy);
        });
      }
      return response;
    }).catch(function () {
      return caches.match(event.request);
    })
  );
});

self.addEventListener('push', function (event) {
  var payload = { title: 'HiveNova', body: '', url: DEFAULT_GAME_URL };
  try {
    if (event.data) {
      payload = Object.assign(payload, event.data.json());
    }
  } catch (e) {}
  event.waitUntil(
    self.registration.showNotification(payload.title, {
      body: payload.body,
      icon: 'favicon.ico',
      data: { url: safeGameUrl(payload.url) }
    })
  );
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  var target = safeGameUrl(event.notification.data && event.notification.data.url);
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
