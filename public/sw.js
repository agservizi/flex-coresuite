self.addEventListener('push', (event) => {
  const title = 'Nuova opportunity';
  const body = 'Un installer ha inviato una nuova segnalazione.';
  const data = { url: '/admin/opportunities.php' };
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
