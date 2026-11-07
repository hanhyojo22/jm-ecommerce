self.addEventListener('push', (event) => {
    const data = event.data?.json() ?? {};

    event.waitUntil(
        self.registration.showNotification(data.title ?? 'JMMart', {
            body: data.body ?? '',
            icon: '/favicon.svg',
            data: { url: data.url ?? '/' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data?.url ?? '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            const existing = clients.find((client) => client.url === url && 'focus' in client);

            return existing ? existing.focus() : self.clients.openWindow(url);
        }),
    );
});
