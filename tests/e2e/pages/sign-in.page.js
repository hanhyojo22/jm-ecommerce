import { BasePage } from './base.page.js';

/**
 * The local authentication entry point at `/sign-in`.
 *
 * Clerk owns the credential form. In the E2E environment the publishable key is
 * intentionally empty, so the page exposes a recoverable fallback inside the
 * same mount point. These locators therefore describe the stable contract
 * between Laravel, Blade, and the Clerk bootstrap rather than Clerk's internal
 * DOM (which is third-party markup and changes without notice).
 */
export class SignInPage extends BasePage {
    static path = '/sign-in';

    /** @param {import('@playwright/test').Page} page */
    constructor(page) {
        super(page);

        // The heading is labelled by `aria-labelledby="auth-heading"` in Blade,
        // making the id a deliberate accessibility contract, not a styling hook.
        this.heading = page.locator('#auth-heading');

        // Tabs are links in the markup, but their role and accessible name are
        // what a user (and assistive technology) actually perceives.
        this.signInTab = page.getByRole('tab', { name: 'Sign in', exact: true });
        this.createAccountTab = page.getByRole('tab', { name: 'Create account', exact: true });

        // `data-clerk-component` is the integration seam consumed by app.js.
        // It survives headings, copy, and CSS changes.
        this.authPanel = page.locator('[data-clerk-component]');
        this.authError = page.locator('[data-clerk-component][role="alert"]');
        this.refreshButton = page.getByRole('button', { name: 'Refresh page' });

        this.browseWithoutAccount = page.getByRole('link', { name: 'Browse without an account' });
    }

    /**
     * Open the sign-in page, optionally preserving a post-auth destination.
     *
     * @param {string} [redirectPath] A local path such as `/cart`.
     * @param {import('@playwright/test').PageGotoOptions} [options]
     */
    async goto(redirectPath, options = {}) {
        const query = redirectPath === undefined
            ? ''
            : `?${new URLSearchParams({ redirect: redirectPath }).toString()}`;

        return super.goto(`${SignInPage.path}${query}`, options);
    }

    async openCreateAccount() {
        await this.createAccountTab.click();
    }

    async openSignIn() {
        await this.signInTab.click();
    }

    async browseAsGuest() {
        await this.browseWithoutAccount.click();
    }
}
