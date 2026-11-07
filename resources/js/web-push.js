/*
 * PC browser push (VAPID + /sw.js). Reverb only refreshes an open tab; this is the
 * path that shows an OS notification when JMMart is closed.
 *
 * Both the Livewire bell and the plain seller-dashboard prompt call these helpers.
 * Livewire is preferred when it is on the page because it shares the component's
 * rate limiter; the HTTP endpoints are a fallback for pages without the bell.
 */

const SERVICE_WORKER_PATH = '/sw.js';
const SERVICE_WORKER_SCOPE = '/';
const SUBSCRIPTIONS_PATH = '/push-subscriptions';
const VAPID_META_SELECTOR = 'meta[name="vapid-public-key"]';
const OPT_OUT_STORAGE_KEY = 'jmmart:web-push-opt-out';

function isPushSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

function isOptedOut() {
    try {
        return window.localStorage.getItem(OPT_OUT_STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

function rememberOptOut(optedOut) {
    try {
        if (optedOut) {
            window.localStorage.setItem(OPT_OUT_STORAGE_KEY, '1');
        } else {
            window.localStorage.removeItem(OPT_OUT_STORAGE_KEY);
        }
    } catch {
        // Storage can be unavailable in private mode; the flow still works without it.
    }
}

/*
 * Only Brave defines navigator.brave. Its presence alone is a reliable sync
 * check; calling the isBrave() method it exposes only adds an async round trip
 * for no extra certainty here.
 */
function isBraveBrowser() {
    return Boolean(window.navigator.brave);
}

function pushState(overrides = {}) {
    const supported = isPushSupported();

    return {
        supported,
        secure: window.isSecureContext !== false,
        permission: supported ? Notification.permission : 'unsupported',
        subscribed: false,
        synced: false,
        error: false,
        optedOut: isOptedOut(),
        isBrave: isBraveBrowser(),
        ...overrides,
    };
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function jsonHeaders() {
    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const token = csrfToken();

    if (token !== '') {
        headers['X-CSRF-TOKEN'] = token;
    }

    return headers;
}

/*
 * Converts the VAPID public key (base64url, as Laravel's webpush config stores it) to the
 * Uint8Array shape PushManager.subscribe() requires for applicationServerKey.
 */
function urlBase64ToUint8Array(base64) {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = window.atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
    const bytes = Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));

    if (bytes.length !== 65 || bytes[0] !== 4) {
        throw new Error('The VAPID public key is not a valid uncompressed P-256 key.');
    }

    return bytes;
}

function keysEqual(left, right) {
    if (left === null || left === undefined) {
        return false;
    }

    const a = left instanceof Uint8Array ? left : new Uint8Array(left);
    const b = right instanceof Uint8Array ? right : new Uint8Array(right);

    return a.length === b.length && a.every((value, index) => value === b[index]);
}

async function vapidPublicKey() {
    const meta = document.querySelector(VAPID_META_SELECTOR)?.content?.trim();

    if (meta) {
        return meta;
    }

    try {
        const response = await fetch('/push/vapid-public-key', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return null;
        }

        const payload = await response.json();

        return typeof payload.key === 'string' && payload.key !== '' ? payload.key : null;
    } catch {
        return null;
    }
}

async function serviceWorkerRegistration({ create = false } = {}) {
    const existing = await navigator.serviceWorker.getRegistration(SERVICE_WORKER_SCOPE);

    if (existing || !create) {
        return existing ?? null;
    }

    const registration = await navigator.serviceWorker.register(SERVICE_WORKER_PATH, {
        scope: SERVICE_WORKER_SCOPE,
        updateViaCache: 'none',
    });

    await navigator.serviceWorker.ready;

    return registration;
}

async function currentSubscription() {
    try {
        const registration = await serviceWorkerRegistration();

        return (await registration?.pushManager.getSubscription()) ?? null;
    } catch {
        return null;
    }
}

async function subscribeOn(registration) {
    const publicKey = await vapidPublicKey();

    if (!publicKey) {
        throw new Error('Desktop notifications are not configured.');
    }

    const applicationServerKey = urlBase64ToUint8Array(publicKey);
    const existing = await registration.pushManager.getSubscription();

    if (existing) {
        // A rotated VAPID keypair cannot reuse the old subscription. Silently
        // resubscribing with the new key is what makes key rotation recoverable.
        if (keysEqual(existing.options?.applicationServerKey, applicationServerKey)) {
            return existing;
        }

        await existing.unsubscribe();
    }

    return registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey,
    });
}

async function endpointHash(endpoint) {
    if (!window.crypto?.subtle) {
        return null;
    }

    const digest = await window.crypto.subtle.digest(
        'SHA-256',
        new TextEncoder().encode(endpoint),
    );

    return [...new Uint8Array(digest)]
        .map((byte) => byte.toString(16).padStart(2, '0'))
        .join('');
}

async function saveSubscription($wire, subscription) {
    const payload = subscription.toJSON();

    if ($wire && typeof $wire.savePushSubscription === 'function') {
        try {
            const result = await $wire.savePushSubscription(payload);

            if (result === true) {
                return true;
            }

            if (result === false) {
                return false;
            }
        } catch (error) {
            console.warn('Livewire could not save the push subscription.', error);
        }
    }

    try {
        const response = await fetch(SUBSCRIPTIONS_PATH, {
            method: 'POST',
            headers: jsonHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        return response.ok;
    } catch (error) {
        console.warn('Could not save the push subscription.', error);

        return false;
    }
}

async function deleteSubscription($wire, endpoint) {
    if ($wire && typeof $wire.removePushSubscription === 'function') {
        try {
            if (await $wire.removePushSubscription(endpoint) === true) {
                return true;
            }
        } catch (error) {
            console.warn('Livewire could not remove the push subscription.', error);
        }
    }

    try {
        const response = await fetch(SUBSCRIPTIONS_PATH, {
            method: 'DELETE',
            headers: jsonHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ endpoint }),
        });

        return response.ok;
    } catch (error) {
        console.warn('Could not remove the push subscription.', error);

        return false;
    }
}

async function syncSubscription($wire, serverEndpointHashes, { autoSave = true } = {}) {
    const state = pushState();

    if (!state.supported || !state.secure) {
        return state;
    }

    if (state.permission !== 'granted') {
        return state;
    }

    if (state.optedOut) {
        const subscription = await currentSubscription();

        return { ...state, subscribed: subscription !== null };
    }

    let registration;

    try {
        registration = await serviceWorkerRegistration({ create: autoSave });
    } catch (error) {
        console.warn('Could not open the notification service worker.', error);

        return { ...state, error: true };
    }

    if (!registration) {
        return state;
    }

    let subscription = null;

    try {
        subscription = await registration.pushManager.getSubscription();

        if (subscription === null && autoSave) {
            subscription = await subscribeOn(registration);
        }
    } catch (error) {
        console.warn('Could not create the browser notification subscription.', error);

        return { ...state, error: true };
    }

    if (subscription === null) {
        return state;
    }

    const hashes = Array.isArray(serverEndpointHashes) ? serverEndpointHashes : [];
    const hash = await endpointHash(subscription.endpoint);
    const synced = hash !== null && hashes.includes(hash);
    const nextState = { ...state, subscribed: true, synced };

    if (synced || !autoSave) {
        return nextState;
    }

    const saved = await saveSubscription($wire, subscription);

    return { ...nextState, synced: saved, error: !saved };
}

/**
 * Reads the browser's current state without prompting. With autoSave it also finishes
 * a subscription that permission already allows (tab closed mid-flow, cleared data).
 */
window.ensureWebPushSubscribed = async ($wire = null, serverEndpointHashes = []) => {
    return syncSubscription($wire, serverEndpointHashes, { autoSave: true });
};

/**
 * Explicit opt-in. This is the only path that asks for Notification permission and
 * the only path that clears a previous "turn off" choice.
 */
window.enableWebPush = async ($wire = null, serverEndpointHashes = []) => {
    const state = pushState();

    if (!state.supported || !state.secure) {
        return state;
    }

    let permission = Notification.permission;

    if (permission === 'default') {
        try {
            permission = await Notification.requestPermission();
        } catch (error) {
            console.warn('Could not request notification permission.', error);
        }
    }

    if (permission !== 'granted') {
        return { ...state, permission };
    }

    rememberOptOut(false);

    try {
        const registration = await serviceWorkerRegistration({ create: true });

        if (!registration) {
            return { ...state, error: true };
        }

        const subscription = await subscribeOn(registration);
        const saved = await saveSubscription($wire, subscription);

        return {
            ...state,
            permission: 'granted',
            subscribed: true,
            synced: saved,
            error: !saved,
        };
    } catch (error) {
        console.warn('Could not enable desktop notifications.', error);

        return { ...state, permission: 'granted', error: true };
    }
};

/**
 * Turns notifications off for this browser. `remember` records the choice so the
 * silent self-heal does not immediately subscribe the user again on the next page.
 */
window.unsubscribeWebPush = async ($wire = null, { remember = false } = {}) => {
    try {
        const registration = await serviceWorkerRegistration();
        const subscription = await registration?.pushManager.getSubscription();

        if (subscription) {
            const endpoint = subscription.endpoint;

            try {
                await subscription.unsubscribe();
            } catch (error) {
                console.warn('Could not unsubscribe the browser.', error);
            }

            await deleteSubscription($wire, endpoint);
        }
    } catch (error) {
        console.warn('Could not remove the browser notification subscription.', error);
    }

    if (remember) {
        rememberOptOut(true);
    }

    return { ...pushState(), subscribed: false, synced: false, error: false };
};

/*
 * The service worker posts this after a browser-initiated subscription change. The bell
 * listens for the DOM event and saves the replacement endpoint to the server.
 */
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', (event) => {
        if (event.data?.type === 'jmmart:pushsubscriptionchange') {
            window.dispatchEvent(new CustomEvent('jmmart:pushsubscriptionchange'));
        }
    });
}
