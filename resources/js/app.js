

import Alpine from 'alpinejs';
import { Clerk } from '@clerk/clerk-js';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Alpine = Alpine;
window.Pusher = Pusher;

if (import.meta.env.VITE_REVERB_APP_KEY) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}

Alpine.start();

const clerkPublishableKey = document.querySelector('meta[name="clerk-publishable-key"]')?.content;

const clerkComponentAppearance = {
    variables: {
        colorPrimary: '#0f172a',
        colorForeground: '#0f172a',
        colorBackground: '#ffffff',
        colorBorder: '#e2e8f0',
        borderRadius: '0.75rem',
        fontFamily: 'Figtree, ui-sans-serif, system-ui, sans-serif',
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

const clerkCustomerAccountAppearance = {
    variables: {
        colorPrimary: '#047857',
        colorForeground: '#111827',
        colorBackground: '#ffffff',
        colorBorder: '#e5e7eb',
        borderRadius: '0.75rem',
        fontFamily: 'Figtree, ui-sans-serif, system-ui, sans-serif',
    },
    elements: {
        rootBox: 'jmmart-clerk-root',
        cardBox: 'jmmart-clerk-card-box',
        card: 'jmmart-clerk-card',
        navbar: 'jmmart-clerk-navbar',
        navbarButton: 'jmmart-clerk-navbar-button',
        navbarButton__active: 'jmmart-clerk-navbar-button-active',
        pageScrollBox: 'jmmart-clerk-page-scroll-box',
        profileSection: 'jmmart-clerk-profile-section',
        profileSectionPrimaryButton: 'jmmart-clerk-profile-action',
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

if (!clerkPublishableKey) {
    renderClerkComponentError('Authentication settings are unavailable. Please refresh the page or try again later.');
} else {
    let clerkDomain;

    try {
        clerkDomain = clerkDomainFromPublishableKey(clerkPublishableKey);
    } catch (error) {
        console.error(error);
    }

    if (!clerkDomain) {
        renderClerkComponentError('Authentication settings are unavailable. Please refresh the page or try again later.');
    }

    if (clerkDomain) {
        const clerkUiScript = document.createElement('script');

        clerkUiScript.src = `https://${clerkDomain}/npm/@clerk/ui@1/dist/ui.browser.js`;
        clerkUiScript.async = true;
        clerkUiScript.crossOrigin = 'anonymous';

        new Promise((resolve, reject) => {
            clerkUiScript.onload = resolve;
            clerkUiScript.onerror = () => reject(new Error('Unable to load Clerk authentication UI.'));
            document.head.appendChild(clerkUiScript);
        }).then(async () => {
            const clerk = new Clerk(clerkPublishableKey);
            window.clerk = clerk;

            await clerk.load({
                afterSignOutUrl: '/',
                signInUrl: document.querySelector('meta[name="clerk-sign-in-url"]')?.content,
                signUpUrl: document.querySelector('meta[name="clerk-sign-up-url"]')?.content,
                ui: { ClerkUI: window.__internal_ClerkUICtor },
            });

            window.dispatchEvent(new Event('clerk:loaded'));
            const signOut = async () => {
                try {
                    await clerk.signOut();
                    window.location.assign('/');
                } catch (error) {
                    console.error(error);
                    window.alert('Unable to sign out. Please try again.');
                }
            };

            document.querySelectorAll('[data-clerk-sign-out]').forEach((button) => {
                button.addEventListener('click', signOut);
            });

            document.querySelectorAll('form[action$="/sign-out"]').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    signOut();
                });
            });

            document.querySelectorAll('[data-clerk-component="sign-in"]').forEach((element) => {
                clerk.mountSignIn(element, {
                    afterSignInUrl: element.dataset.afterAuthUrl,
                    appearance: clerkComponentAppearance,
                });
            });

            document.querySelectorAll('[data-clerk-component="sign-up"]').forEach((element) => {
                clerk.mountSignUp(element, {
                    afterSignUpUrl: element.dataset.afterAuthUrl,
                    appearance: clerkComponentAppearance,
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
                clerk.mountUserProfile(element, {
                    appearance: element.closest('.jmmart-account-shell') ? clerkCustomerAccountAppearance : clerkComponentAppearance,
                });
            });
        }).catch((error) => {
            console.error(error);
            renderClerkComponentError();
        });
    }
}
