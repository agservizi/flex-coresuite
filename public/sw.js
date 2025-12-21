const CACHE_NAME = 'flex-coresuite-v1';
const STATIC_CACHE = 'flex-static-v1';

// Risorse da cachare
const STATIC_ASSETS = [
  '/',
  '/index.php',
  '/assets/css/style.css',
  '/public/logo-flex.svg',
  '/public/favicon.svg',
  '/public/manifest.json'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => {
      return cache.addAll(STATIC_ASSETS);
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== STATIC_CACHE && cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  // Gestisci solo richieste GET
  if (event.request.method !== 'GET') return;

  // Strategia cache-first per risorse statiche
  if (event.request.url.includes('/assets/') ||
      event.request.url.includes('/public/') ||
      event.request.url.endsWith('.css') ||
      event.request.url.endsWith('.js') ||
      event.request.url.endsWith('.svg') ||
      event.request.url.endsWith('.png') ||
      event.request.url.endsWith('.jpg')) {
    event.respondWith(
      caches.match(event.request).then((response) => {
        return response || fetch(event.request).then((response) => {
          // Cache nuove risorse statiche
          if (response.status === 200) {
            const responseClone = response.clone();
            caches.open(STATIC_CACHE).then((cache) => {
              cache.put(event.request, responseClone);
            });
          }
          return response;
        });
      })
    );
  } else {
    // Network-first per pagine dinamiche
    event.respondWith(
      fetch(event.request).then((response) => {
        // Cache pagine dinamiche se necessario
        if (response.status === 200 && event.request.destination === 'document') {
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseClone);
          });
        }
        return response;
      }).catch(() => {
        // Fallback alla cache se offline
        return caches.match(event.request);
      })
    );
  }
});

// Svuota cache automaticamente ogni ora
setInterval(() => {
  caches.keys().then((cacheNames) => {
    cacheNames.forEach((cacheName) => {
      caches.delete(cacheName);
    });
  });
}, 60 * 60 * 1000); // 1 ora

self.addEventListener('push', (event) => {
  let title = 'Nuova opportunity';
  let body = 'Un installer ha inviato una nuova segnalazione.';
  let data = { url: '/admin/opportunities.php' };

  if (event.data) {
    try {
      const payload = event.data.json();
      title = payload.title || title;
      body = payload.body || body;
    } catch (e) {
      // ignore
    }
  }

  event.waitUntil(
    self.registration.showNotification(title, {
      body,
      data,
      icon: '/public/logo-flex.svg',
      badge: '/public/logo-flex.svg',
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = event.notification.data && event.notification.data.url ? event.notification.data.url : '/admin/opportunities.php';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if (client.url.includes(targetUrl) && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
      return undefined;
    })
  );
});
