
import './cookie-consent';
import { showRequestError } from './request-errors';
import './chat';
import './notifications';
import './web-push';
import './cart';
import { clerkComponentAppearance, clerkUserProfileAppearance } from './clerk-appearance';

window.marketplaceSuggestions = (initialQuery, url, resultsUrl) => ({
    query: initialQuery,
    items: [],
    open: false,
    loading: false,
    active: -1,
    url,
    resultsUrl,
    request: null,
    async load() {
        const query = this.query.trim();
        this.request?.abort();
        this.items = [];
        this.active = -1;
        if ([...query].length < 2) {
            this.open = false;
            this.loading = false;
            return;
        }
        this.open = true;
        this.loading = true;
        const request = new AbortController();
        this.request = request;
        try {
            const response = await fetch(`${this.url}?q=${encodeURIComponent(query)}`, {
                signal: request.signal,
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error('Search suggestions failed');
            const data = await response.json();
            if (this.request === request) this.items = data.items;
        } catch (error) {
            if (error.name !== 'AbortError') this.open = false;
        } finally {
            if (this.request === request) this.loading = false;
        }
    },
    move(direction) {
        if (!this.open || !this.items.length) return;
        this.active = (this.active + direction + this.items.length) % this.items.length;
    },
    select(event) {
        if (!this.open || this.active < 0) return;
        event.preventDefault();
        window.location.href = this.items[this.active].url;
    },
    clear() {
        this.request?.abort();
        this.query = '';
        this.items = [];
        this.open = false;
        this.loading = false;
        this.active = -1;
    },
});

const reverbConfig = {
    key: document.querySelector('meta[name="reverb-app-key"]')?.content,
    host: document.querySelector('meta[name="reverb-host"]')?.content,
    port: document.querySelector('meta[name="reverb-port"]')?.content,
    scheme: document.querySelector('meta[name="reverb-scheme"]')?.content,
};

if (document.querySelector('[data-conversation-thread], [data-conversation-inbox], [data-notification-bell]') && reverbConfig.key && reverbConfig.host) {
    Promise.all([import('laravel-echo'), import('pusher-js')]).then(([{ default: Echo }, { default: Pusher }]) => {
        window.Pusher = Pusher;
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbConfig.key,
            wsHost: reverbConfig.host,
            wsPort: Number(reverbConfig.port ?? 8080),
            wssPort: Number(reverbConfig.port ?? 443),
            forceTLS: (reverbConfig.scheme ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        });
        window.dispatchEvent(new Event('reverb:ready'));
    }).catch(() => console.warn('Real-time updates are unavailable; polling remains active.'));
}

const clerkPublishableKey = document.querySelector('meta[name="clerk-publishable-key"]')?.content;
const clerkComponents = document.querySelectorAll('[data-clerk-component]');
const clerkSignOutControls = document.querySelectorAll('[data-clerk-sign-out]');
const clerkHandoff = document.querySelector('[data-clerk-session-handoff]');
const clerkSessionRefreshEndpoint = document.querySelector('meta[name="clerk-session-refresh-endpoint"]')?.content;

const sellerDashboard = document.querySelector('[data-seller-dashboard]');

if (sellerDashboard) {
    if (window.matchMedia('(max-width: 639px)').matches) {
        sellerDashboard.querySelector('[data-seller-guide]')?.removeAttribute('open');
        sellerDashboard.querySelector('[data-seller-responsive-panel]')?.removeAttribute('open');
    }

    const dashboardKey = `seller-dashboard:${sellerDashboard.dataset.dashboardKey}`;
    const dashboardSections = [...sellerDashboard.querySelectorAll('[data-dashboard-section]')];
    const dashboardToggles = [...sellerDashboard.querySelectorAll('[data-dashboard-toggle]')];
    let dashboardPreferences = {};

    try {
        const savedPreferences = document.querySelector('[data-cookie-consent]')?.dataset.functional === 'true'
            ? JSON.parse(window.localStorage.getItem(dashboardKey) || '{}')
            : {};
        dashboardPreferences = savedPreferences && typeof savedPreferences === 'object' && !Array.isArray(savedPreferences)
            ? savedPreferences
            : {};
    } catch {
        dashboardPreferences = {};
    }

    const showDashboardSection = (name, visible) => {
        const section = dashboardSections.find((item) => item.dataset.dashboardSection === name);
        const toggle = dashboardToggles.find((item) => item.dataset.dashboardToggle === name);

        if (section) {
            section.hidden = !visible;
        }

        if (toggle) {
            toggle.checked = visible;
        }
    };

    dashboardToggles.forEach((toggle) => {
        const savedVisibility = dashboardPreferences[toggle.dataset.dashboardToggle];
        showDashboardSection(toggle.dataset.dashboardToggle, typeof savedVisibility === 'boolean' ? savedVisibility : true);

        toggle.addEventListener('change', () => {
            dashboardPreferences[toggle.dataset.dashboardToggle] = toggle.checked;
            showDashboardSection(toggle.dataset.dashboardToggle, toggle.checked);

            if (document.querySelector('[data-cookie-consent]')?.dataset.functional === 'true') {
                try {
                    window.localStorage.setItem(dashboardKey, JSON.stringify(dashboardPreferences));
                } catch {
                    // Keep the current page's preference when browser storage is unavailable.
                }
            }
        });
    });

    sellerDashboard.querySelector('[data-dashboard-reset]')?.addEventListener('click', () => {
        dashboardPreferences = {};
        dashboardToggles.forEach((toggle) => showDashboardSection(toggle.dataset.dashboardToggle, true));

        try {
            window.localStorage.removeItem(dashboardKey);
        } catch {
            // Defaults are restored for this page even when browser storage is unavailable.
        }
    });
}

document.querySelectorAll('form[data-confirm-message]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (![...form.elements].some((element) => element.type === 'checkbox' && element.checked)) {
            event.preventDefault();
            window.alert('Select at least one item first.');

            return;
        }

        if (!window.confirm(form.dataset.confirmMessage)) {
            event.preventDefault();
        }
    });
});

/*
 * An in-page confirmation in place of the browser's own confirm() box, laid out as a claim slip:
 * the question on top, and on the tear-off stub what the action touches and what it changes.
 * A form opts in with data-confirm (the explanation) and optionally data-confirm-title,
 * data-confirm-reference (such as "Order JM-…"), data-confirm-amount, data-confirm-from and
 * data-confirm-to (the status before and after), data-confirm-action, data-confirm-dismiss and
 * data-confirm-tone="danger". One dialog is built on first use.
 */
let confirmDialog;

const askToConfirm = ({ confirm: message, confirmTitle, confirmReference, confirmAmount, confirmFrom, confirmTo, confirmAction, confirmDismiss, confirmTone }) => {
    if (!confirmDialog) {
        confirmDialog = document.createElement('dialog');
        confirmDialog.className = 'confirm-dialog';
        confirmDialog.setAttribute('aria-labelledby', 'confirm-dialog-title');
        confirmDialog.setAttribute('aria-describedby', 'confirm-dialog-message');
        confirmDialog.innerHTML = '<form method="dialog">'
            + '<div class="confirm-slip"><h2 id="confirm-dialog-title" class="confirm-slip-title"></h2><p id="confirm-dialog-message" class="confirm-slip-message"></p></div>'
            + '<div class="confirm-stub">'
            + '<div class="confirm-stub-line"><p class="confirm-stub-reference"></p><p class="confirm-stub-change"><span class="confirm-stub-from"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 12h15m-5-5 5 5-5 5"/></svg><span class="sr-only"> to </span><span class="confirm-stub-to"></span></p></div>'
            + '<p class="confirm-stub-amount"></p>'
            + '<div class="confirm-stub-actions"><button type="submit" value="cancel" class="confirm-dismiss"></button><button type="submit" value="confirm" class="confirm-commit"></button></div>'
            + '</div></form>';
        confirmDialog.addEventListener('click', (event) => {
            if (event.target === confirmDialog) {
                confirmDialog.close('cancel');
            }
        });
        document.body.append(confirmDialog);
        // The edge notches sit on the tear line, which moves with the length of the question.
        const slip = confirmDialog.querySelector('.confirm-slip');
        new ResizeObserver(() => confirmDialog.style.setProperty('--slip-tear', `${slip.offsetHeight}px`)).observe(slip);
    }

    const fill = (selector, text) => {
        const element = confirmDialog.querySelector(selector);
        element.textContent = text || '';
        element.hidden = !text;
    };
    const tone = confirmTone === 'danger' ? 'danger' : 'default';
    confirmDialog.dataset.tone = tone;
    fill('.confirm-slip-title', confirmTitle || 'Are you sure?');
    fill('.confirm-slip-message', message);
    fill('.confirm-stub-reference', confirmReference);
    fill('.confirm-stub-amount', confirmAmount);
    fill('.confirm-stub-from', confirmFrom);
    fill('.confirm-stub-to', confirmTo);
    confirmDialog.querySelector('.confirm-stub-change').hidden = !confirmTo;
    confirmDialog.querySelector('.confirm-stub-line').hidden = !confirmReference && !confirmTo;
    fill('.confirm-dismiss', confirmDismiss || 'Cancel');
    fill('.confirm-commit', confirmAction || 'Continue');
    confirmDialog.returnValue = '';
    confirmDialog.showModal();
    // A destructive action starts on the safe choice, so Enter alone never commits it.
    confirmDialog.querySelector(tone === 'danger' ? '.confirm-dismiss' : '.confirm-commit').focus();

    return new Promise((resolve) => {
        confirmDialog.addEventListener('close', () => resolve(confirmDialog.returnValue === 'confirm'), { once: true });
    });
};

/*
 * Capture phase, so the session refresh below never sees an unconfirmed submit. Its own
 * resubmit after renewing the cookie is already confirmed and passes straight through.
 * Once sent, the button shows it is working and a second press cannot send the form twice.
 */
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) {
        return;
    }

    if (form.dataset.confirmed === 'true' || form.dataset.clerkSessionRefreshed === 'true') {
        delete form.dataset.confirmed;

        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    if (confirmDialog?.open || form.hasAttribute('data-submitting')) {
        return;
    }

    const submitter = event.submitter;

    askToConfirm(form.dataset).then((confirmed) => {
        if (!confirmed) {
            return;
        }

        form.dataset.confirmed = 'true';
        if (submitter) {
            form.requestSubmit(submitter);
        } else {
            form.requestSubmit();
        }
        form.setAttribute('data-submitting', '');
        submitter?.setAttribute('aria-busy', 'true');
    });
}, true);

// A page restored with the back button must not keep its buttons stuck in the sending state.
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting]').forEach((form) => {
        form.removeAttribute('data-submitting');
        form.querySelector('[aria-busy="true"]')?.removeAttribute('aria-busy');
    });
});

const clerkAuthAppearance = {
    ...clerkComponentAppearance,
    elements: {
        ...clerkComponentAppearance.elements,
        header: { display: 'none' },
    },
};

/*
 * Every step of a sign-in hand-off — the bundle, Clerk, the token, the server — sits behind a
 * request that can stall without ever failing, and a stalled promise leaves the page waiting
 * with nothing to report. Callers here would rather show an error than wait forever.
 */
const withTimeout = (work, milliseconds, message) => new Promise((resolve, reject) => {
    const timer = window.setTimeout(() => reject(new Error(message)), milliseconds);

    Promise.resolve(work).then(
        (value) => {
            window.clearTimeout(timer);
            resolve(value);
        },
        (error) => {
            window.clearTimeout(timer);
            reject(error);
        },
    );
});

const renderClerkComponentError = (message = 'Authentication could not load. Please refresh the page.') => {
    document.querySelectorAll('[data-clerk-component]').forEach((element) => {
        const description = document.createElement('p');
        const retryButton = document.createElement('button');

        description.textContent = message;
        retryButton.type = 'button';
        retryButton.textContent = 'Refresh page';
        retryButton.addEventListener('click', () => window.location.reload());

        element.replaceChildren(description, retryButton);
        element.setAttribute('aria-busy', 'false');
        element.setAttribute('role', 'alert');
        element.classList.add('space-y-3', 'rounded-lg', 'border', 'border-red-200', 'bg-red-50', 'p-4', 'text-sm', 'text-red-800');
        retryButton.classList.add('inline-flex', 'min-h-11', 'items-center', 'justify-center', 'rounded-lg', 'bg-white', 'px-4', 'font-semibold', 'text-red-800', 'ring-1', 'ring-inset', 'ring-red-300', 'transition', 'hover:bg-red-100', 'focus-visible:outline-none', 'focus-visible:ring-2', 'focus-visible:ring-red-700', 'focus-visible:ring-offset-2');
    });
};

const renderClerkHandoffError = (message = 'We could not finish signing you in.') => {
    if (!clerkHandoff) {
        return;
    }

    /*
     * The page ships the error block next to the spinner so that a bundle which never ran still
     * leaves a way out. When the page's own state object exists, it owns the swap so the dialog
     * label and focus follow the error; when it does not — a blocked inline script — this path
     * must not depend on it, so the swap happens here directly.
     */
    if (typeof window.clerkHandoffState?.fail === 'function') {
        window.clerkHandoffState.fail(message);

        return;
    }

    window.clerkHandoffState?.finish();

    const errorBlock = document.querySelector('[data-clerk-handoff-error]');

    if (!errorBlock) {
        return;
    }

    const title = errorBlock.querySelector('[data-clerk-handoff-error-title]');

    if (title) {
        title.textContent = message;
    }

    clerkHandoff.setAttribute('hidden', '');
    errorBlock.removeAttribute('hidden');
};

const clerkHandoffTokenTimeout = 10_000;
const clerkHandoffPostTimeout = 15_000;

/*
 * The page starts a deadline of its own, long enough to say that this bundle never arrived but
 * shorter than the chain below. Each step that is reached buys the deadline the time that step
 * is allowed, so a hand-off that is still working is never told it has failed — and a step with
 * no deadline of its own, such as reading the response body, is still covered.
 */
const clerkHandoffDeadlineSlack = 2_000;

const completeClerkHandoff = async (clerk) => {
    if (!clerkHandoff) {
        return;
    }

    if (!clerk.user) {
        renderClerkHandoffError('Your Clerk sign-in could not be confirmed.');
        return;
    }

    window.clerkHandoffState?.stage('session');
    window.clerkHandoffState?.extend(clerkHandoffTokenTimeout + clerkHandoffDeadlineSlack);

    const sessionToken = await withTimeout(
        clerk.session?.getToken(),
        clerkHandoffTokenTimeout,
        'Clerk did not hand back a session token in time.',
    );

    if (!sessionToken) {
        throw new Error('Clerk did not provide a session token for the sign-in handoff.');
    }

    window.clerkHandoffState?.stage('server');
    window.clerkHandoffState?.extend(clerkHandoffPostTimeout + clerkHandoffDeadlineSlack);

    const abort = new AbortController();
    const abortTimer = window.setTimeout(() => abort.abort(), clerkHandoffPostTimeout);
    let response;

    try {
        response = await fetch(clerkHandoff.dataset.sessionEndpoint, {
            signal: abort.signal,
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                Authorization: `Bearer ${sessionToken}`,
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ redirect: clerkHandoff.dataset.redirectUrl }),
        });
    } catch (error) {
        throw new Error('The server did not answer the sign-in handoff.', { cause: error });
    } finally {
        window.clearTimeout(abortTimer);
    }

    if (!response.ok) {
        throw new Error(`Laravel rejected the Clerk sign-in handoff (${response.status}).`);
    }

    const result = await response.json();

    if (typeof result.redirect !== 'string' || !result.redirect.startsWith('/') || result.redirect.startsWith('//')) {
        throw new Error('Laravel returned an invalid sign-in destination.');
    }

    window.clerkHandoffState?.stage('done');
    window.clerkHandoffState?.finish();
    window.location.assign(result.redirect);
};

const clerkDomainFromPublishableKey = (publishableKey) => {
    const encodedDomain = publishableKey.split('_')[2];

    if (!encodedDomain) {
        return null;
    }

    const decodedDomain = atob(encodedDomain);

    if (!decodedDomain.endsWith('$')) {
        return null;
    }

    const domain = decodedDomain.slice(0, -1);
    const url = new URL(`https://${domain}`);

    return url.hostname ? url.host : null;
};

const clearClerkSessionCookie = () => {
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';

    document.cookie = `__session=; Max-Age=0; path=/${secure}; SameSite=Lax`;
};

let clerkLoadPromise;

/*
 * Clerk's UI script and Clerk's own boot each sit behind a network request. Without a deadline
 * a stalled request leaves every page that mounts a Clerk component waiting on it forever.
 */
const clerkLoadTimeout = 15_000;

const loadClerk = () => {
    if (window.clerk) {
        return Promise.resolve(window.clerk);
    }

    if (clerkLoadPromise) {
        return clerkLoadPromise;
    }

    if (!clerkPublishableKey) {
        return Promise.reject(new Error('Clerk authentication settings are unavailable.'));
    }

    let clerkDomain;

    try {
        clerkDomain = clerkDomainFromPublishableKey(clerkPublishableKey);
    } catch (error) {
        return Promise.reject(error);
    }

    if (!clerkDomain) {
        return Promise.reject(new Error('Clerk authentication settings are unavailable.'));
    }

    const clerkUiScript = document.createElement('script');
    clerkUiScript.src = `https://${clerkDomain}/npm/@clerk/ui@1/dist/ui.browser.js`;
    clerkUiScript.async = true;
    clerkUiScript.crossOrigin = 'anonymous';

    clerkLoadPromise = withTimeout(new Promise((resolve, reject) => {
        clerkUiScript.onload = resolve;
        clerkUiScript.onerror = () => reject(new Error('Unable to load Clerk authentication UI.'));
        document.head.appendChild(clerkUiScript);
    }), clerkLoadTimeout, 'Unable to load Clerk authentication UI.').then(async () => {
        const { Clerk } = await import('@clerk/clerk-js');
        const clerk = new Clerk(clerkPublishableKey);

        await withTimeout(clerk.load({
            afterSignOutUrl: '/',
            signInUrl: document.querySelector('meta[name="clerk-sign-in-url"]')?.content,
            signUpUrl: document.querySelector('meta[name="clerk-sign-up-url"]')?.content,
            ui: { ClerkUI: window.__internal_ClerkUICtor },
        }), clerkLoadTimeout, 'Clerk did not finish loading.');

        clerk.addListener(({ session }) => {
            if (! session) {
                clearClerkSessionCookie();
            }
        });

        window.clerk = clerk;

        return clerk;
    });

    return clerkLoadPromise;
};

/*
 * Clerk session tokens are valid for about 60 seconds and the backend cookie is minted to
 * match, so a protected page opened after that window carries no verifiable credential and
 * Laravel refuses it. The check below only decides *when to ask Clerk for another token*;
 * it is never proof of identity. A missing or unreadable cookie always asks, and the server
 * verifies every request on its own.
 */
const clerkSessionRefreshLeeway = 15_000;

const clerkSessionExpiryFromToken = (token) => {
    const payload = token.split('.')[1];

    if (!payload) {
        return null;
    }

    try {
        const base64 = payload.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(payload.length / 4) * 4, '=');
        const exp = JSON.parse(atob(base64)).exp;

        return typeof exp === 'number' ? exp * 1000 : null;
    } catch {
        return null;
    }
};

const readClerkSessionToken = () => document.cookie
    .split('; ')
    .find((entry) => entry.startsWith('__session='))
    ?.slice('__session='.length) || null;

/*
 * The backend only accepts the __session cookie. A remembered deadline cannot stand in for
 * a cookie that is gone, so a missing or unreadable cookie always asks Clerk for another.
 */
const clerkSessionNeedsRefresh = () => {
    const expiresAt = clerkSessionExpiryFromToken(readClerkSessionToken() ?? '');

    return expiresAt === null || expiresAt - Date.now() <= clerkSessionRefreshLeeway;
};

const refreshClerkSession = async (endpoint) => {
    const controller = new AbortController();
    let timer;

    try {
        await Promise.race([
            (async () => {
                const clerk = await loadClerk();
                const sessionToken = await clerk.session?.getToken();

                if (!sessionToken) {
                    throw new Error('Clerk did not provide a session token for the navigation request.');
                }

                const response = await fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    signal: controller.signal,
                    headers: {
                        Accept: 'application/json',
                        Authorization: `Bearer ${sessionToken}`,
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                });

                if (!response.ok) {
                    throw new Error(`Laravel rejected the Clerk session refresh (${response.status}).`);
                }

                const result = await response.json().catch(() => null);

                if (result?.refreshed !== true) {
                    throw new Error('Laravel did not confirm the Clerk session refresh.');
                }

                if (!readClerkSessionToken()) {
                    throw new Error('The refreshed Clerk session cookie was not stored.');
                }
            })(),
            new Promise((_, reject) => {
                timer = setTimeout(() => {
                    controller.abort();
                    reject(new Error('Session refresh timed out.'));
                }, 8000);
            }),
        ]);
    } finally {
        clearTimeout(timer);
    }
};

/*
 * One refresh at a time, whoever asks. A link click, a form submit and a Livewire request that
 * overlap used to mint a cookie each, because only the Livewire path shared its in-flight call.
 */
let pendingClerkSessionRefresh;

/** Needing a live cookie from here on shares a call; a rejected credential mints its own. */
const ensureClerkSession = (endpoint) => {
    pendingClerkSessionRefresh ??= refreshClerkSession(endpoint)
        .finally(() => { pendingClerkSessionRefresh = null; });

    return pendingClerkSessionRefresh;
};

/*
 * The forms whose navigation needs a live session cookie. The submit handler below refreshes for
 * exactly these, and the pointerdown warm starts that refresh while the press is still happening
 * instead of after it, so the wait overlaps the click rather than following it.
 */
const formNeedsClerkSessionCookie = (form) => {
    if (!(form instanceof HTMLFormElement) || form.method.toUpperCase() === 'GET') {
        return false;
    }

    if (form.hasAttribute('wire:submit') || form.hasAttribute('data-add-to-cart')) {
        return false;
    }

    let action;

    try {
        action = new URL(form.action);
    } catch {
        return false;
    }

    return action.origin === window.location.origin && action.pathname !== '/sign-out';
};

// Livewire sends its own fetch requests, including chat typing and Send, without submitting a form.
if (clerkSessionRefreshEndpoint) {
    const browserFetch = window.fetch.bind(window);

    window.fetch = async (input, options) => {
        const needsSession = options?.headers?.['X-Livewire'] === '1'
            || options?.headers?.['X-Requires-Session'] === '1';

        if (needsSession && clerkSessionNeedsRefresh()) {
            try {
                await ensureClerkSession(clerkSessionRefreshEndpoint);
                document.querySelector('[data-clerk-refresh-error]')?.remove();
            } catch (error) {
                const form = document.activeElement?.closest('form[wire\\:submit]')
                    ?? document.querySelector('form[wire\\:submit="submit"], [data-conversation-thread] form[wire\\:submit="send"]');

                if (form && !form.querySelector('[data-clerk-refresh-error]')) {
                    const message = document.createElement('p');
                    message.dataset.clerkRefreshError = '';
                    message.className = 'text-sm font-medium text-red-700';
                    message.setAttribute('role', 'alert');
                    message.textContent = 'Your session could not be refreshed. Please try again.';
                    form.prepend(message);
                }

                throw error;
            }
        }

        return browserFetch(input, options);
    };
}

/*
 * Navigation renews the session before it opens a page, rather than each link opting in and
 * remembering to. A session that is still fresh costs no extra request at all, so only the
 * links that would actually be refused pay for a refresh.
 */
document.addEventListener('click', (event) => {
    if (!clerkSessionRefreshEndpoint || !clerkSessionNeedsRefresh()) {
        return;
    }

    const link = event.target instanceof Element ? event.target.closest('a[href]') : null;

    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    if (link.hasAttribute('download') || (link.target && link.target !== '_self')) {
        return;
    }

    const href = link.getAttribute('href');

    if (!href || href.startsWith('#') || /^(?:mailto|tel|sms):/i.test(href)) {
        return;
    }

    let destination;

    try {
        destination = new URL(link.href, window.location.href);
    } catch {
        return;
    }

    if (destination.origin !== window.location.origin) {
        return;
    }

    event.preventDefault();
    link.setAttribute('aria-busy', 'true');

    ensureClerkSession(clerkSessionRefreshEndpoint)
        .catch((error) => {
            console.warn('Session refresh failed.');
        })
        .finally(() => {
            window.location.assign(destination.href);
        });
});

document.addEventListener('submit', async (event) => {
    const form = event.target;

    if (!formNeedsClerkSessionCookie(form)) {
        return;
    }

    const refreshEndpoint = form.dataset.clerkSessionRefresh || clerkSessionRefreshEndpoint;

    if (!refreshEndpoint) {
        return;
    }

    if (form.dataset.clerkSessionRefreshed === 'true') {
        delete form.dataset.clerkSessionRefreshed;

        return;
    }

    if (!clerkSessionNeedsRefresh()) {
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    if (form.dataset.clerkSessionRefreshing === 'true') {
        return;
    }

    form.dataset.clerkSessionRefreshing = 'true';

    /*
     * The submit is swallowed for the length of the refresh, so without this the page looks dead
     * while it waits. The submitter is re-enabled before the real submit, because a cart quantity
     * button carries the new quantity in its own name and value.
     */
    const submitter = event.submitter;
    form.setAttribute('aria-busy', 'true');
    if (submitter) {
        submitter.disabled = true;
    }

    const clearBusy = () => {
        form.removeAttribute('aria-busy');
        if (submitter) {
            submitter.disabled = false;
        }
    };

    try {
        await ensureClerkSession(refreshEndpoint);
        form.dataset.clerkSessionRefreshed = 'true';
        delete form.dataset.clerkSessionRefreshing;
        clearBusy();
        if (submitter) {
            form.requestSubmit(submitter);
        } else {
            form.requestSubmit();
        }
    } catch (error) {
        delete form.dataset.clerkSessionRefreshing;
        clearBusy();
        console.warn('Session refresh failed.');

        if (form.hasAttribute('data-chat-upload')) {
            form.dispatchEvent(new CustomEvent('chat-upload-error', { bubbles: true, detail: { message: 'Your session could not be refreshed. Use Retry to try again.' } }));
            return;
        }

        const message = document.createElement('p');
        message.className = 'mt-3 text-sm font-semibold text-danger';
        message.setAttribute('role', 'alert');
        message.textContent = 'Your session could not be refreshed. Please refresh the page and try again.';
        form.prepend(message);
    }
}, true);

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-chat-upload') || event.defaultPrevented) {
        return;
    }

    event.preventDefault();
    if (form.dataset.uploading === 'true') {
        return;
    }

    form.dataset.uploading = 'true';
    const request = new XMLHttpRequest();
    request.open('POST', form.action);
    request.responseType = 'json';
    request.timeout = 20000;
    request.setRequestHeader('Accept', 'application/json');
    request.upload.addEventListener('progress', (progress) => {
        if (progress.lengthComputable) {
            form.dispatchEvent(new CustomEvent('chat-upload-progress', { bubbles: true, detail: { progress: Math.round(progress.loaded / progress.total * 100) } }));
        }
    });
    request.addEventListener('load', () => {
        delete form.dataset.uploading;
        if (request.status >= 200 && request.status < 300 && request.response?.message === 'Attachment sent.') {
            form.dispatchEvent(new CustomEvent('chat-upload-sent', { bubbles: true, detail: request.response }));
            return;
        }

        const errors = request.response?.errors;
        const message = errors ? Object.values(errors).flat()[0] : request.response?.message;
        form.dispatchEvent(new CustomEvent('chat-upload-error', { bubbles: true, detail: { message: message || 'Upload failed. Use Retry to try again.' } }));
    });
    request.addEventListener('error', () => {
        delete form.dataset.uploading;
        form.dispatchEvent(new CustomEvent('chat-upload-error', { bubbles: true, detail: { message: 'Upload failed. Check your connection and use Retry.' } }));
    });
    request.addEventListener('timeout', () => {
        delete form.dataset.uploading;
        form.dispatchEvent(new CustomEvent('chat-upload-error', { bubbles: true, detail: { message: 'Upload timed out. Use Retry to check whether it was saved.' } }));
    });
    request.send(new FormData(form));
});

const newArrivalsSection = document.querySelector('[data-new-arrivals]');
newArrivalsSection?.querySelector('[data-new-arrivals-more]')?.addEventListener('click', async (event) => {
    event.preventDefault();
    const button = event.currentTarget;
    const grid = newArrivalsSection.querySelector('[data-new-arrivals-grid]');
    const status = newArrivalsSection.querySelector('[data-new-arrivals-status]');
    const skeleton = newArrivalsSection.querySelector('[data-new-arrivals-skeleton]');
    if (button.getAttribute('aria-disabled') === 'true' || !newArrivalsSection.dataset.nextUrl) {
        return;
    }

    button.setAttribute('aria-disabled', 'true');
    button.textContent = 'Loading…';
    grid.setAttribute('aria-busy', 'true');
    skeleton.hidden = false;
    status.textContent = '';

    try {
        const response = await fetch(newArrivalsSection.dataset.nextUrl, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            throw new Error('Could not load more products. Please try again.');
        }

        const result = await response.json();
        if (typeof result.html !== 'string') {
            throw new Error('Could not load more products. Please try again.');
        }

        const fragment = document.createElement('template');
        fragment.innerHTML = result.html;
        const count = fragment.content.children.length;
        const firstNewLink = fragment.content.querySelector('a');
        fragment.content.querySelectorAll('form[data-add-to-cart]').forEach(bindAddToCartForm);
        grid.append(fragment.content);
        newArrivalsSection.dataset.nextUrl = result.next_url ?? '';
        if (result.next_url) {
            button.href = result.next_url;
        }
        status.setAttribute('role', 'status');
        status.className = 'text-sm text-muted';
        status.textContent = count ? `${count} more products loaded.` : 'All new arrivals are shown.';
        if (!result.next_url) {
            button.remove();
        }
        firstNewLink?.focus();
    } catch (error) {
        status.setAttribute('role', 'alert');
        status.className = 'text-sm text-danger';
        status.textContent = error.message || 'Could not load more products. Please try again.';
    } finally {
        grid.removeAttribute('aria-busy');
        skeleton.hidden = true;
        button.removeAttribute('aria-disabled');
        button.textContent = 'Load More';
    }
});

const cartLink = document.querySelector('[data-cart-link]');
let confirmedCartCount = Number(cartLink?.getAttribute('aria-label')?.match(/\d+/)?.[0] ?? 0);
let pendingCartQuantity = 0;
let concurrentCartAdds = false;

let renderedCartCount = confirmedCartCount;
let cartBadgePop;
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

const renderCartCount = () => {
    if (!cartLink) return;

    const count = confirmedCartCount + pendingCartQuantity;
    const changed = count !== renderedCartCount;
    renderedCartCount = count;

    cartLink.setAttribute('aria-label', `Cart: ${count} ${count === 1 ? 'item' : 'items'}`);

    const badge = cartLink.querySelector('[data-cart-badge]');
    if (!badge) return;

    badge.textContent = count > 99 ? '99+' : String(count);
    badge.style.display = count > 0 ? '' : 'none';

    /*
     * The count is already correct by here, so the beat is decoration the badge can lose: a burst
     * of adds replaces the motion in flight instead of queueing behind it, and nothing waits on it
     * finishing. The page does not move when the count changes, so without this the number swaps
     * silently and the add reads as if it never landed.
     */
    if (changed && count > 0 && !reducedMotion.matches) {
        cartBadgePop?.cancel();
        cartBadgePop = badge.animate?.(
            [{ transform: 'scale(1)' }, { transform: 'scale(1.3)' }, { transform: 'scale(1)' }],
            { duration: 260, easing: 'cubic-bezier(0.34, 1.56, 0.64, 1)' },
        );
    }
};

/*
 * Clerk session tokens live 60 seconds, so an add to cart almost always found the backend
 * session lapsed and spent a whole round trip on the bridge minting a cookie before the cart
 * request could even start. The token rides on the cart request itself instead, and the bridge
 * stays as the fallback for a token the server rejects. Overlapping clicks share one lookup.
 */
let pendingClerkToken;

const clerkSessionToken = () => {
    pendingClerkToken ??= loadClerk()
        .then((clerk) => clerk.session?.getToken())
        .catch(() => null)
        .finally(() => { pendingClerkToken = null; });

    return pendingClerkToken;
};

const bindAddToCartForm = (form) => {
    form.addEventListener('submit', async (event) => {
        if (event.defaultPrevented) {
            return;
        }

        event.preventDefault();

        if (form.dataset.cartSubmitting === 'true') {
            return;
        }

        form.dataset.cartSubmitting = 'true';
        const button = event.submitter;
        if (button) {
            button.disabled = true;
        }

        const quantity = Number(new FormData(form).get('quantity'));
        const optimisticQuantity = Number.isInteger(quantity) && quantity >= 1 && quantity <= 100 ? quantity : 0;
        if (pendingCartQuantity > 0) concurrentCartAdds = true;
        pendingCartQuantity += optimisticQuantity;
        renderCartCount();
        form.setAttribute('aria-busy', 'true');

        const feedback = form.querySelector('[data-cart-feedback]') ?? document.createElement('p');
        feedback.dataset.cartFeedback = '';
        feedback.className = 'mt-2 text-sm font-semibold text-muted';
        feedback.setAttribute('role', 'status');
        feedback.textContent = 'Adding to cart…';
        form.append(feedback);

        try {
            const headers = { Accept: 'application/json' };

            if (form.dataset.clerkSessionRefresh) {
                const sessionToken = await clerkSessionToken();

                if (sessionToken) {
                    headers.Authorization = `Bearer ${sessionToken}`;
                }
            }

            const submit = () => fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers,
                body: new FormData(form),
            });

            let response = await submit();
            if (response.status === 401 && form.dataset.clerkSessionRefresh) {
                await refreshClerkSession(form.dataset.clerkSessionRefresh);
                // Clerk reads the header in preference to the cookie, so the rejected token has to
                // go for the cookie the bridge just minted to be the one that counts.
                delete headers.Authorization;
                response = await submit();
            }

            const result = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(result.errors?.variant_id?.[0] ?? result.errors?.quantity?.[0] ?? result.message ?? 'Could not add this item.');
            }

            const count = result.cart_count;
            if (!Number.isInteger(count)) {
                throw new Error('Could not update your cart. Please refresh the page.');
            }

            // shortcut: overlapping adds only increase this page's count; reload after edits in another tab.
            confirmedCartCount = concurrentCartAdds ? Math.max(confirmedCartCount, count) : count;

            if (button?.dataset.checkoutUrl) {
                /*
                 * Buy now leaves on a plain navigation, which carries cookies and no header, so
                 * the session this request authenticated itself with has to be put in a cookie
                 * first. Skipping it would land on the bridge page and redirect from there.
                 */
                if (form.dataset.clerkSessionRefresh && clerkSessionNeedsRefresh()) {
                    await ensureClerkSession(form.dataset.clerkSessionRefresh).catch(() => {});
                }

                window.location.assign(button.dataset.checkoutUrl);
                return;
            }

            feedback.setAttribute('role', 'status');
            feedback.className = 'mt-2 text-sm font-semibold text-success';
            feedback.textContent = 'Added to cart.';
        } catch (error) {
            feedback.setAttribute('role', 'alert');
            feedback.className = 'mt-2 text-sm font-semibold text-danger';
            feedback.textContent = error.message || 'Could not add this item. Please try again.';
        } finally {
            pendingCartQuantity -= optimisticQuantity;
            if (pendingCartQuantity === 0) concurrentCartAdds = false;
            renderCartCount();
            form.removeAttribute('aria-busy');
            delete form.dataset.cartSubmitting;
            if (button) {
                button.disabled = false;
            }
        }
    });
};

/*
 * Clerk is loaded, and the credential the submit needs fetched, while the pointer is still down,
 * so the press pays for the wait rather than the click that follows it. An add to cart needs a
 * token for its own request; every other form needs the cookie its navigation will carry.
 */
document.addEventListener('pointerdown', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    if (event.target.closest('form[data-add-to-cart][data-clerk-session-refresh]')) {
        clerkSessionToken();

        return;
    }

    const submitter = event.target.closest('button:not([type="button"]), input[type="submit"]');
    const form = submitter?.form;
    const endpoint = form?.dataset.clerkSessionRefresh || clerkSessionRefreshEndpoint;

    if (endpoint && formNeedsClerkSessionCookie(form) && clerkSessionNeedsRefresh()) {
        ensureClerkSession(endpoint).catch(() => {});
    }
}, { passive: true });

document.querySelectorAll('form[data-add-to-cart]').forEach(bindAddToCartForm);

document.querySelector('[data-seller-reviews]')?.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-discussion-reply-form]');
    if (!form) {
        return;
    }

    event.preventDefault();
    if (form.dataset.submitting === 'true') {
        return;
    }

    form.dataset.submitting = 'true';
    const button = form.querySelector('button[type="submit"]');
    const feedback = form.querySelector('[data-discussion-feedback]');
    button.disabled = true;
    button.textContent = 'Posting...';
    feedback.hidden = true;

    try {
        const submit = () => fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            body: new FormData(form),
        });

        let response = await submit();
        if (response.status === 401) {
            await refreshClerkSession(form.dataset.clerkSessionRefresh);
            response = await submit();
        }

        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(result.errors?.body?.[0] ?? result.errors?.parent_id?.[0] ?? result.message ?? 'Could not post your reply.');
        }
        if (!result.html) {
            window.location.reload();
            return;
        }

        form.closest('.seller-review-discussion').insertAdjacentHTML('beforeend', result.html);
        form.reset();
        form.closest('details').open = false;
        document.querySelector('[data-discussion-announcement]').textContent = 'Reply posted.';
    } catch (error) {
        feedback.textContent = error.message || 'Could not confirm your reply. Refresh before trying again.';
        feedback.hidden = false;
    } finally {
        delete form.dataset.submitting;
        button.disabled = false;
        button.textContent = 'Post reply';
    }
});

const signOut = async () => {
    try {
        const clerk = await loadClerk();
        // A browser notification subscription still points at this account otherwise — the
        // next person signed in on this device would get this account's alerts as OS toasts.
        await window.unsubscribeWebPush?.();
        clearClerkSessionCookie();
        await clerk.signOut();
        window.location.assign('/');
    } catch (error) {
        showRequestError('Unable to sign out. Please try again.');
    }
};

clerkSignOutControls.forEach((button) => {
    button.addEventListener('click', (event) => {
        event.preventDefault();
        signOut();
    });
});

document.querySelectorAll('form[action$="/sign-out"]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        signOut();
    });
});

const sellerHelpSearch = document.querySelector('[data-seller-help-search]');
const storeLogoInput = document.querySelector('input[name="logo"]');
const removeStoreLogoInput = document.querySelector('input[name="remove_logo"]');

storeLogoInput?.addEventListener('change', () => {
    if (storeLogoInput.files.length > 0 && removeStoreLogoInput) {
        removeStoreLogoInput.checked = false;
    }
});

removeStoreLogoInput?.addEventListener('change', () => {
    if (removeStoreLogoInput.checked && storeLogoInput) {
        storeLogoInput.value = '';
    }
});

if (sellerHelpSearch) {
    const sellerHelpItems = [...document.querySelectorAll('[data-seller-help-item]')];
    const sellerHelpEmpty = document.querySelector('[data-seller-help-empty]');

    sellerHelpSearch.addEventListener('input', () => {
        const query = sellerHelpSearch.value.trim().toLocaleLowerCase();
        let visibleItems = 0;

        sellerHelpItems.forEach((item) => {
            const matches = item.textContent.toLocaleLowerCase().includes(query);
            item.hidden = !matches;
            visibleItems += Number(matches);
        });

        sellerHelpEmpty?.classList.toggle('hidden', visibleItems > 0);
    });
}

if (document.querySelector('[data-seller-shortcut="search"]')) {
    document.addEventListener('keydown', (event) => {
        const target = event.target;

        if (event.defaultPrevented || event.repeat || event.altKey || event.ctrlKey || event.metaKey
            || target instanceof HTMLElement && (target.isContentEditable || target.matches('input, textarea, select, [role="textbox"]'))
            || document.querySelector('dialog[open]')) {
            return;
        }

        if (event.key === '?') {
            const guide = document.querySelector('[data-seller-guide]');
            const helpSearch = document.querySelector('[data-seller-help-search]');

            if (guide && helpSearch) {
                event.preventDefault();
                if (guide.hidden) {
                    guide.hidden = false;
                    const helpToggle = sellerDashboard?.querySelector('[data-dashboard-toggle="help"]');
                    if (helpToggle) {
                        helpToggle.checked = true;
                        helpToggle.dispatchEvent(new Event('change'));
                    }
                }
                guide.open = true;
                helpSearch.focus();
            }

            return;
        }

        const shortcuts = { s: 'search', n: 'new-product', o: 'orders' };
        const shortcut = shortcuts[event.key.toLocaleLowerCase()];

        if (!shortcut) {
            return;
        }

        const element = [...document.querySelectorAll(`[data-seller-shortcut="${shortcut}"]`)]
            .find((candidate) => candidate.getClientRects().length > 0);

        if (!element) {
            return;
        }

        event.preventDefault();

        if (shortcut === 'search') {
            element.focus();
        } else {
            element.click();
        }
    });
}

if (clerkComponents.length > 0 || clerkHandoff) {
    if (!clerkPublishableKey) {
        renderClerkComponentError('Authentication settings are unavailable. Please refresh the page or try again later.');
        renderClerkHandoffError('Authentication settings are unavailable. Please try again later.');
    } else {
        // Loading Clerk is two requests in turn, each with the deadline above.
        window.clerkHandoffState?.extend((clerkLoadTimeout * 2) + clerkHandoffDeadlineSlack);

        loadClerk().then(async (clerk) => {
            await completeClerkHandoff(clerk);
            window.dispatchEvent(new Event('clerk:loaded'));

            document.querySelectorAll('[data-clerk-component="sign-in"]').forEach((element) => {
                clerk.mountSignIn(element, {
                    forceRedirectUrl: element.dataset.afterAuthUrl,
                    appearance: clerkAuthAppearance,
                });
            });

            document.querySelectorAll('[data-clerk-component="sign-up"]').forEach((element) => {
                clerk.mountSignUp(element, {
                    forceRedirectUrl: element.dataset.afterAuthUrl,
                    appearance: clerkAuthAppearance,
                });
            });

            document.querySelectorAll('[data-clerk-component="user-button"]').forEach((element) => {
                clerk.mountUserButton(element, {
                    userProfileMode: 'navigation',
                    userProfileUrl: '/profile',
                    appearance: clerkComponentAppearance,
                    userProfileProps: { appearance: clerkUserProfileAppearance },
                });
            });

            document.querySelectorAll('[data-clerk-component="user-profile"]').forEach((element) => {
                element.replaceChildren();
                element.setAttribute('aria-busy', 'false');
                clerk.mountUserProfile(element, { appearance: clerkUserProfileAppearance });
            });
        }).catch((error) => {
            console.warn('Authentication could not load.');
            renderClerkComponentError();
            renderClerkHandoffError();
        });
    }
}
