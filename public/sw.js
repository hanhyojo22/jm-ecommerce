/*
 * PC browser push service worker.
 *
 * The Laravel web-push channel nests its payload under `data`, so a notification
 * created with ->data(['url' => ...]) arrives here as payload.data.url. Reading
 * payload.url directly is why earlier notification clicks always landed on "/".
 */

const DEFAULT_ICON = '/images/notification-icon.png';
const DEFAULT_BADGE = '/images/notification-badge.png';

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

function parsePayload(event) {
    if (!event.data) {
        return {};
    }

    try {
        const payload = event.data.json();

        return payload && typeof payload === 'object' ? payload : {};
    } catch {
        try {
            return { body: event.data.text() };
        } catch {
            return {};
        }
    }
}

function text(value, fallback = '') {
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : fallback;
}

function sameOriginUrl(value, fallbackPath = '/') {
    const fallback = new URL(fallbackPath, self.location.origin);

    if (typeof value !== 'string' || value === '') {
        return fallback;
    }

    try {
        const url = new URL(value, self.location.origin);

        return url.origin === self.location.origin ? url : fallback;
    } catch {
        return fallback;
    }
}

function notificationImage(value) {
    if (typeof value !== 'string' || value === '') {
        return null;
    }

    try {
        const url = new URL(value, self.location.origin);

        // External artwork must be HTTPS; local development may serve it same-origin.
        return url.protocol === 'https:' || url.origin === self.location.origin ? url : null;
    } catch {
        return null;
    }
}

function signedReadUrl(value) {
    const url = sameOriginUrl(value);

    return url.pathname.startsWith('/push-notifications/') && url.pathname.endsWith('/read')
        ? url
        : null;
}

function notificationOptions(payload) {
    const data = payload.data && typeof payload.data === 'object' ? payload.data : {};
    const tag = text(payload.tag ?? data.tag);
    const image = notificationImage(payload.image ?? data.image_url);
    const options = {
        body: text(payload.body),
        icon: text(payload.icon, DEFAULT_ICON),
        badge: text(payload.badge, DEFAULT_BADGE),
        data: {
            url: sameOriginUrl(data.url).href,
            read_url: signedReadUrl(data.read_url)?.href ?? null,
            notification_id: text(data.notification_id),
        },
        timestamp: Date.now(),
    };

    if (image !== null) {
        options.image = image.href;
    }

    // renotify requires a tag in Chromium; without one it throws instead of showing.
    if (tag !== '') {
        options.tag = tag;
        options.renotify = payload.renotify !== false;
    }

    if (payload.requireInteraction === true) {
        options.requireInteraction = true;
    }

    return options;
}

self.addEventListener('push', (event) => {
    const payload = parsePayload(event);
    const title = text(payload.title, 'JMMart');

    event.waitUntil(
        self.registration.showNotification(title, notificationOptions(payload)),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const data = event.notification.data && typeof event.notification.data === 'object'
        ? event.notification.data
        : {};

    // The click opens the app, but the database notification should be marked read
    // even when the tab that opens is signed out or the OS opens a fresh window. This
    // runs as its own waitUntil so a slow request never delays the window focus/open
    // below past the click's brief user-activation window.
    const readUrl = signedReadUrl(data.read_url);

    if (readUrl !== null) {
        event.waitUntil(
            fetch(readUrl.href, { credentials: 'same-origin', cache: 'no-store' }).catch(() => {
                // A failed mark-read must never stop the notification from opening.
            }),
        );
    }

    event.waitUntil((async () => {
        const target = sameOriginUrl(data.url);
        const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        const samePath = clients.find((client) => {
            try {
                const url = new URL(client.url);

                return url.origin === target.origin && url.pathname === target.pathname;
            } catch {
                return false;
            }
        });

        if (samePath) {
            if ('navigate' in samePath && samePath.url !== target.href) {
                try {
                    await samePath.navigate(target.href);
                } catch {
                    // Focusing the existing tab is still better than failing the click.
                }
            }

            return samePath.focus();
        }

        // Reuse another tab from this site instead of stacking a new one for every alert.
        const sameOrigin = clients.find((client) => {
            try {
                return new URL(client.url).origin === target.origin;
            } catch {
                return false;
            }
        });

        if (sameOrigin && 'navigate' in sameOrigin) {
            try {
                await sameOrigin.navigate(target.href);

                return sameOrigin.focus();
            } catch {
                // Fall through and open a window.
            }
        }

        return self.clients.openWindow(target.href);
    })());
});

/*
 * Browsers rotate or expire push subscriptions on their own. Re-subscribe right away
 * so the browser keeps a live endpoint, then let an open page save it to the server.
 * If no page is open, the next page load's self-heal saves it.
 */
self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil((async () => {
        let subscription = event.newSubscription ?? null;

        if (subscription === null && event.oldSubscription?.options?.applicationServerKey) {
            try {
                subscription = await self.registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: event.oldSubscription.options.applicationServerKey,
                });
            } catch {
                subscription = null;
            }
        }

        const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        clients.forEach((client) => {
            client.postMessage({
                type: 'jmmart:pushsubscriptionchange',
                subscription: subscription?.toJSON?.() ?? null,
            });
        });
    })());
});
