// PERF-006 (web push follow-up): scoped to /admin/ only (see the
// {scope: '/admin/'} registration in useWebPushSms.ts) — this never runs
// for a storefront page. Shows an OS notification for every push, and also
// messages any open admin tab so it can update in place without the admin
// having to click the notification.
self.addEventListener('push', (event) => {
    let payload = { title: 'New notification', body: '', data: {} };

    try {
        if (event.data) {
            payload = { ...payload, ...event.data.json() };
        }
    } catch {
        // Malformed payload — still show a generic notification rather than nothing.
    }

    event.waitUntil(
        (async () => {
            await self.registration.showNotification(payload.title, {
                body: payload.body,
                icon: '/favicon.ico',
                data: payload,
            });

            const clients = await self.clients.matchAll({ type: 'window' });
            for (const client of clients) {
                client.postMessage({ source: 'admin-push-sw', payload });
            }
        })(),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url ?? '/admin/received-sms';

    event.waitUntil(
        (async () => {
            const clients = await self.clients.matchAll({ type: 'window' });
            const existing = clients.find((client) => client.url.includes('/admin'));

            if (existing) {
                existing.focus();
                existing.postMessage({ source: 'admin-push-sw', payload: event.notification.data });

                return;
            }

            await self.clients.openWindow(url);
        })(),
    );
});
