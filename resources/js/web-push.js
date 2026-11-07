/*
 * Converts the VAPID public key (base64url, as Laravel's webpush config stores it) to the
 * Uint8Array shape PushManager.subscribe() requires for applicationServerKey.
 */
function urlBase64ToUint8Array(base64) {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = window.atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));

    return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
}

/*
 * Shared by the explicit "enable" click and the silent restore below. Never prompts for
 * permission itself — the caller decides whether that's appropriate.
 */
async function subscribeAndSave($wire) {
    const vapidPublicKey = document.querySelector('meta[name="vapid-public-key"]')?.content;

    if (!vapidPublicKey) {
        return false;
    }

    const registration = await navigator.serviceWorker.register('/sw.js');
    const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
    });

    await $wire.savePushSubscription(subscription.toJSON());

    return true;
}

window.unsubscribeWebPush = async () => {
    try {
        const registration = await navigator.serviceWorker?.getRegistration();
        const subscription = await registration?.pushManager.getSubscription();

        if (!subscription) {
            return;
        }

        const endpoint = subscription.endpoint;
        await subscription.unsubscribe();
        // Drops the server-side row too — unsubscribing the browser alone leaves it orphaned
        // until the next failed send happens to prune it, which could be a long time.
        await document.querySelector('[data-notification-bell]')?.$wire?.removePushSubscription(endpoint);
    } catch {
        // Best-effort: a browser without push support, or nothing to unsubscribe, is fine.
    }
};

window.enableWebPush = async ($wire) => {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        return false;
    }

    try {
        const permission = await Notification.requestPermission();

        if (permission !== 'granted') {
            return false;
        }

        return await subscribeAndSave($wire);
    } catch (error) {
        console.warn('Could not enable browser notifications.', error);

        return false;
    }
};

/*
 * Permission can be granted (this session or an earlier one) without a subscription ever
 * having been saved — the tab was closed mid-flow, the save request failed, browser data
 * was cleared, etc. Nothing here prompts the user; it only finishes what permission already
 * allows, so it's safe to run on every page load.
 */
window.ensureWebPushSubscribed = async ($wire) => {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || Notification.permission !== 'granted') {
        return;
    }

    try {
        const registration = await navigator.serviceWorker.register('/sw.js');
        const existing = await registration.pushManager.getSubscription();

        if (existing) {
            return;
        }

        await subscribeAndSave($wire);
    } catch (error) {
        console.warn('Could not restore the browser notification subscription.', error);
    }
};
