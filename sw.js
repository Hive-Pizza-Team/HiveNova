const CACHE_NAME = 'hivenova-static-v4';
const DEFAULT_GAME_URL = 'game.php?page=overview';
const PING_TYPE = 'hivenova-ping';
const PONG_TYPE = 'hivenova-pong';

function safeGameUrl(url) {
  if (typeof url !== 'string' || url === '') {
    return DEFAULT_GAME_URL;
  }
  if (/^game\.php\?page=[a-zA-Z0-9_]+$/.test(url)) {
    return url;
  }
  if (/^\/uni[0-9]+\/game\.php\?page=[a-zA-Z0-9_]+$/.test(url)) {
    return url;
  }
  return DEFAULT_GAME_URL;
}

function notificationIconUrl() {
  try {
    var scope = (self.registration && self.registration.scope) || (self.location && self.location.href) || '/';
    return new URL('styles/resource/images/pwa/icon-192.png', scope).href;
  } catch (e) {
    return '/styles/resource/images/pwa/icon-192.png';
  }
}

function parsePushPayload(event) {
  var fallback = {
    title: 'HiveNova',
    body: 'New update',
    url: DEFAULT_GAME_URL,
    tag: 'hivenova'
  };
  if (!event || !event.data) {
    return fallback;
  }
  try {
    var parsed = event.data.json();
    if (parsed && typeof parsed === 'object') {
      var nested = parsed.data && typeof parsed.data === 'object' ? parsed.data : {};
      return {
        title: parsed.title || fallback.title,
        body: parsed.body || fallback.body,
        url: parsed.url || nested.url || fallback.url,
        tag: parsed.tag || nested.type || fallback.tag
      };
    }
  } catch (e) {
    try {
      var text = event.data.text();
      if (typeof text === 'string' && text !== '') {
        fallback.body = text;
      }
    } catch (e2) {}
  }
  return fallback;
}

function notificationOptions(payload) {
  return {
    body: payload.body || 'HiveNova',
    icon: notificationIconUrl(),
    tag: payload.tag || 'hivenova',
    renotify: true,
    silent: false,
    requireInteraction: true,
    data: { url: safeGameUrl(payload.url) }
  };
}

function handlePushEvent(event) {
  var payload = parsePushPayload(event);
  return self.registration.showNotification(payload.title, notificationOptions(payload));
}

// Precache only stable assets; main.css uses network-first (see fetch handler).
const CACHE_URLS = [
  './styles/resource/css/tokens.css',
  './scripts/game/base.js',
  './styles/resource/images/pwa/icon-192.png'
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
  event.waitUntil(handlePushEvent(event));
});

self.addEventListener('message', function (event) {
  var data = event.data || {};
  if (data.type !== PING_TYPE) {
    return;
  }
  var port = event.ports && event.ports[0];
  var reply = {
    ok: true,
    type: PONG_TYPE,
    scope: (self.registration && self.registration.scope) || '',
    scriptURL: (self.registration && self.registration.active && self.registration.active.scriptURL)
      || (self.location && self.location.href)
      || '',
    hasPushSubscription: false
  };
  var send = function () {
    if (port) {
      port.postMessage(reply);
    }
  };
  if (!self.registration || !self.registration.pushManager) {
    send();
    return;
  }
  event.waitUntil(
    self.registration.pushManager.getSubscription().then(function (sub) {
      reply.hasPushSubscription = !!sub;
      send();
    }).catch(function () {
      send();
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

if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    CACHE_NAME: CACHE_NAME,
    DEFAULT_GAME_URL: DEFAULT_GAME_URL,
    PING_TYPE: PING_TYPE,
    PONG_TYPE: PONG_TYPE,
    safeGameUrl: safeGameUrl,
    notificationIconUrl: notificationIconUrl,
    parsePushPayload: parsePushPayload,
    notificationOptions: notificationOptions,
    handlePushEvent: handlePushEvent
  };
}
