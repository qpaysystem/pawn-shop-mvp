// Service Worker для личного кабинета (PWA)
const CACHE_NAME = 'cabinet-pwa-v1';

self.addEventListener('install', function (event) {
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (names) {
      return Promise.all(
        names.filter(function (name) { return name !== CACHE_NAME; }).map(function (name) { return caches.delete(name); })
      );
    }).then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('push', function (event) {
  var data = { title: 'Личный кабинет', body: '', url: '/cabinet' };
  if (event.data) {
    try {
      var payload = event.data.json();
      if (payload.title) data.title = payload.title;
      if (payload.body) data.body = payload.body;
      if (payload.url) data.url = payload.url;
    } catch (e) {}
  }
  event.waitUntil(
    self.registration.showNotification(data.title, {
      body: data.body,
      icon: '/images/pwa-icon-192.png',
      badge: '/images/pwa-icon-192.png',
      tag: 'transaction',
      data: { url: data.url }
    })
  );
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  var url = event.notification.data && event.notification.data.url ? event.notification.data.url : '/cabinet';
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
      for (var i = 0; i < clientList.length; i++) {
        if (clientList[i].url.indexOf(self.location.origin) === 0) {
          clientList[i].navigate(url);
          clientList[i].focus();
          return;
        }
      }
      if (self.clients.openWindow) self.clients.openWindow(url);
    })
  );
});

self.addEventListener('fetch', function (event) {
  var url = new URL(event.request.url);
  // Только страницы кабинета и GET
  if (url.origin !== location.origin || !url.pathname.startsWith('/cabinet') || event.request.method !== 'GET') {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then(function (response) {
        var clone = response.clone();
        if (response.status === 200 && response.type === 'basic') {
          caches.open(CACHE_NAME).then(function (cache) { cache.put(event.request, clone); });
        }
        return response;
      })
      .catch(function () {
        return caches.match(event.request).then(function (cached) {
          return cached || caches.match('/cabinet');
        });
      })
  );
});
