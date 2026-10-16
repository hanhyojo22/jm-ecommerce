
import './cookie-consent';
import './chat';
import './cart';

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

if (document.querySelector('[data-conversation-thread], [data-conversation-inbox]') && reverbConfig.key && reverbConfig.host) {
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
    }).catch((error) => console.error(error));
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

const clerkComponentAppearance = {
    variables: {
        colorPrimary: '#2563eb',
        colorForeground: '#0f172a',
        colorBackground: '#ffffff',
        colorBorder: '#e2e8f0',
        borderRadius: '10px',
        fontFamily: 'Inter, ui-sans-serif, system-ui, sans-serif',
    },
    options: {
        elevation: 'flush',
    },
    elements: {
        rootBox: {
            width: '100%',
            maxWidth: '100%',
            minWidth: '0',
        },
        cardBox: {
            width: '100%',
            maxWidth: '100%',
            minWidth: '0',
        },
        card: {
            width: '100%',
            maxWidth: '100%',
            minWidth: '0',
        },
    },
};

const clerkAuthAppearance = {
    ...clerkComponentAppearance,
    elements: {
        ...clerkComponentAppearance.elements,
        header: { display: 'none' },
    },
};

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
        element.classList.add('space-y-3', 'rounded-xl', 'border', 'border-red-200', 'bg-red-50', 'p-4', 'text-sm', 'text-red-800');
        retryButton.classList.add('inline-flex', 'min-h-11', 'items-center', 'justify-center', 'rounded-lg', 'bg-white', 'px-4', 'font-semibold', 'text-red-800', 'ring-1', 'ring-inset', 'ring-red-300', 'transition', 'hover:bg-red-100', 'focus-visible:outline-none', 'focus-visible:ring-2', 'focus-visible:ring-red-700', 'focus-visible:ring-offset-2');
    });
};

const renderClerkHandoffError = (message = 'We could not finish signing you in.') => {
    if (!clerkHandoff) {
        return;
    }

    const title = document.createElement('p');
    const description = document.createElement('p');
    const signInLink = document.createElement('a');

    title.className = 'font-semibold text-slate-900';
    title.textContent = message;
    description.className = 'text-sm text-slate-600';
    description.textContent = 'Please return to sign in and try again.';
    signInLink.className = 'inline-flex min-h-11 items-center justify-center rounded-[10px] bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700';
    signInLink.href = clerkHandoff.dataset.signInUrl || '/sign-in';
    signInLink.textContent = 'Return to sign in';

    clerkHandoff.setAttribute('role', 'alert');
    clerkHandoff.replaceChildren(title, description, signInLink);
};

const completeClerkHandoff = async (clerk) => {
    if (!clerkHandoff) {
        return;
    }

    if (!clerk.user) {
        renderClerkHandoffError('Your Clerk sign-in could not be confirmed.');
        return;
    }

    const sessionToken = await clerk.session?.getToken();

    if (!sessionToken) {
        throw new Error('Clerk did not provide a session token for the sign-in handoff.');
    }

    rememberClerkSessionDeadline(sessionToken);

    const response = await fetch(clerkHandoff.dataset.sessionEndpoint, {
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

    if (!response.ok) {
        throw new Error(`Laravel rejected the Clerk sign-in handoff (${response.status}).`);
    }

    const result = await response.json();

    if (typeof result.redirect !== 'string' || !result.redirect.startsWith('/') || result.redirect.startsWith('//')) {
        throw new Error('Laravel returned an invalid sign-in destination.');
    }

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

let clerkLoadPromise;

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

    clerkLoadPromise = new Promise((resolve, reject) => {
        clerkUiScript.onload = resolve;
        clerkUiScript.onerror = () => reject(new Error('Unable to load Clerk authentication UI.'));
        document.head.appendChild(clerkUiScript);
    }).then(async () => {
        const { Clerk } = await import('@clerk/clerk-js');
        const clerk = new Clerk(clerkPublishableKey);

        await clerk.load({
            afterSignOutUrl: '/',
            signInUrl: document.querySelector('meta[name="clerk-sign-in-url"]')?.content,
            signUpUrl: document.querySelector('meta[name="clerk-sign-up-url"]')?.content,
            ui: { ClerkUI: window.__internal_ClerkUICtor },
        });

        window.clerk = clerk;

        return clerk;
    });

    return clerkLoadPromise;
};

/*
 * Clerk session tokens are valid for about 60 seconds and the backend cookie is minted to
 * match, so a protected page opened after that window carries no verifiable credential and
 * Laravel refuses it. The deadline below only decides *when to ask Clerk for another token*;
 * it is never proof of identity. The server verifies every request on its own.
 */
const clerkSessionDeadlineKey = 'clerk_session_expires_at';
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
 * The browser deletes the cookie the moment it lapses, so the cookie on its own cannot tell a
 * fresh session from one that has already gone. The remembered deadline is what still answers
 * that question once the cookie is gone.
 */
const clerkSessionDeadline = () => {
    const remembered = Number(window.localStorage.getItem(clerkSessionDeadlineKey));
    const fromCookie = clerkSessionExpiryFromToken(readClerkSessionToken() ?? '');

    return Math.max(Number.isFinite(remembered) ? remembered : 0, fromCookie ?? 0) || null;
};

const rememberClerkSessionDeadline = (token) => {
    const expiresAt = clerkSessionExpiryFromToken(token);

    if (!expiresAt) {
        return;
    }

    try {
        window.localStorage.setItem(clerkSessionDeadlineKey, String(expiresAt));
    } catch {
        // Without storage the cookie's own expiry is still read on the next page.
    }
};

const forgetClerkSessionDeadline = () => {
    try {
        window.localStorage.removeItem(clerkSessionDeadlineKey);
    } catch {
        // There is nothing to forget when storage is unavailable.
    }
};

const clerkSessionNeedsRefresh = () => {
    const deadline = clerkSessionDeadline();

    // A signed-in visitor whose deadline cannot be measured refreshes rather than guess.
    return deadline === null || deadline - Date.now() <= clerkSessionRefreshLeeway;
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

                rememberClerkSessionDeadline(sessionToken);
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

// Livewire sends its own fetch requests, including chat typing and Send, without submitting a form.
if (clerkSessionRefreshEndpoint) {
    const browserFetch = window.fetch.bind(window);
    let pendingClerkSessionRefresh;

    window.fetch = async (input, options) => {
        const needsSession = options?.headers?.['X-Livewire'] === '1'
            || options?.headers?.['X-Requires-Session'] === '1';

        if (needsSession && clerkSessionNeedsRefresh()) {
            pendingClerkSessionRefresh ??= refreshClerkSession(clerkSessionRefreshEndpoint)
                .finally(() => { pendingClerkSessionRefresh = null; });

            try {
                await pendingClerkSessionRefresh;
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

    refreshClerkSession(clerkSessionRefreshEndpoint)
        .catch((error) => {
            console.error(error);
        })
        .finally(() => {
            window.location.assign(destination.href);
        });
});

document.addEventListener('submit', async (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const action = new URL(form.action);

    if (form.method.toUpperCase() === 'GET' || action.origin !== window.location.origin || action.pathname === '/sign-out' || form.hasAttribute('wire:submit')) {
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

    try {
        await refreshClerkSession(refreshEndpoint);
        form.dataset.clerkSessionRefreshed = 'true';
        delete form.dataset.clerkSessionRefreshing;
        if (event.submitter) {
            form.requestSubmit(event.submitter);
        } else {
            form.requestSubmit();
        }
    } catch (error) {
        delete form.dataset.clerkSessionRefreshing;
        console.error(error);

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
    if (button.getAttribute('aria-disabled') === 'true' || !newArrivalsSection.dataset.nextUrl) {
        return;
    }

    button.setAttribute('aria-disabled', 'true');
    button.textContent = 'Loading…';
    grid.setAttribute('aria-busy', 'true');
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
        button.removeAttribute('aria-disabled');
        button.textContent = 'Load More';
    }
});

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

        const feedback = form.querySelector('[data-cart-feedback]') ?? document.createElement('p');
        feedback.dataset.cartFeedback = '';
        feedback.className = 'mt-2 text-sm font-semibold';
        form.append(feedback);

        try {
            const submit = () => fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                body: new FormData(form),
            });

            let response = await submit();
            if (response.status === 401 && form.dataset.clerkSessionRefresh) {
                await refreshClerkSession(form.dataset.clerkSessionRefresh);
                response = await submit();
            }

            const result = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(result.errors?.variant_id?.[0] ?? result.errors?.quantity?.[0] ?? result.message ?? 'Could not add this item.');
            }

            const count = result.cart_count;
            const cartLink = document.querySelector('[data-cart-link]');
            if (!Number.isInteger(count)) {
                throw new Error('Could not update your cart. Please refresh the page.');
            }

            if (button?.dataset.checkoutUrl) {
                window.location.assign(button.dataset.checkoutUrl);
                return;
            }

            if (cartLink) {
                cartLink.setAttribute('aria-label', `Cart: ${count} ${count === 1 ? 'item' : 'items'}`);
                const badge = cartLink.querySelector('[data-cart-badge]');
                if (badge) {
                    badge.textContent = count > 99 ? '99+' : String(count);
                    badge.style.display = count > 0 ? '' : 'none';
                }
                const countText = cartLink.querySelector('[data-cart-count-text]');
                if (countText) {
                    countText.textContent = `Cart (${count})`;
                }
            }

            feedback.setAttribute('role', 'status');
            feedback.classList.add('text-success');
            feedback.textContent = 'Added to cart.';
        } catch (error) {
            feedback.setAttribute('role', 'alert');
            feedback.classList.add('text-danger');
            feedback.textContent = error.message || 'Could not add this item. Please try again.';
        } finally {
            delete form.dataset.cartSubmitting;
            if (button) {
                button.disabled = false;
            }
        }
    });
};

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
        await clerk.signOut();
        forgetClerkSessionDeadline();
        window.location.assign('/');
    } catch (error) {
        console.error(error);
        window.alert('Unable to sign out. Please try again.');
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
        renderClerkHandoffError();
    } else {
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
                    userProfileProps: { appearance: clerkComponentAppearance },
                });
            });

            document.querySelectorAll('[data-clerk-component="user-profile"]').forEach((element) => {
                element.replaceChildren();
                element.setAttribute('aria-busy', 'false');
                clerk.mountUserProfile(element);
            });
        }).catch((error) => {
            console.error(error);
            renderClerkComponentError();
            renderClerkHandoffError();
        });
    }
}
