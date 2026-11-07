/*
 * Converts the VAPID public key (base64url, as Laravel's webpush config stores it) to the
 * Uint8Array shape PushManager.subscribe() requires for applicationServerKey.
 */
function urlBase64ToUint8Array(base64) {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = window.atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));

    return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
}

window.unsubscribeWebPush = async () => {
    try {
        const registration = await navigator.serviceWorker?.getRegistration();
        const subscription = await registration?.pushManager.getSubscription();
        await subscription?.unsubscribe();
    } catch {
        // Best-effort: a browser without push support, or nothing to unsubscribe, is fine.
    }
};

window.enableWebPush = async ($wire) => {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        return false;
    }

    const vapidPublicKey = document.querySelector('meta[name="vapid-public-key"]')?.content;

    if (!vapidPublicKey) {
        return false;
    }

    try {
        const permission = await Notification.requestPermission();

        if (permission !== 'granted') {
            return false;
        }

        const registration = await navigator.serviceWorker.register('/sw.js');
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
        });

        await $wire.savePushSubscription(subscription.toJSON());

        return true;
    } catch (error) {
        console.warn('Could not enable browser notifications.', error);

        return false;
    }
};
