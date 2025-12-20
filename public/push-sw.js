const SW_VERSION = '1.0.0';

self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    const data = safeParse(event?.data?.text?.());
    const title = data.title || 'PolyLife Reminder';
    const body = data.body || 'Reminder mendekati tenggat.';
    const tag = data.tag || `polylife-reminder-${Date.now()}`;
    const url = data.url || data?.data?.url || '/workspace/dashboard';

    const options = {
        body,
        tag,
        icon: data.icon || '/favicon.ico',
        badge: data.badge || data.icon || '/favicon.ico',
        data: {
            url,
            ...data.data,
        },
        renotify: true,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = event.notification?.data?.url || '/workspace/dashboard';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url.includes(targetUrl) && 'focus' in client) {
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(targetUrl);
            }
            return null;
        })
    );
});

self.addEventListener('pushsubscriptionchange', async () => {
    const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    clients.forEach((client) => client.postMessage({ type: 'PUSH_SUBSCRIPTION_CHANGED' }));
});

function safeParse(raw) {
    try {
        return raw ? JSON.parse(raw) : {};
    } catch (e) {
        return {};
    }
}
