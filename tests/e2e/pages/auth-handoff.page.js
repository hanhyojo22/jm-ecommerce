import { BasePage } from './base.page.js';

/**
 * The same-origin bridge that exchanges a Clerk session token for a Laravel
 * session cookie and then forwards the visitor to their requested page.
 *
 * The two states share a page intentionally: the spinner is present in the
 * initial HTML, while the error block is already in the DOM but hidden. That
 * means a failed script or request can still leave the visitor with a way out.
 */
export class AuthHandoffPage extends BasePage {
    static path = '/auth/complete';

    /** @param {import('@playwright/test').Page} page */
    constructor(page) {
        super(page);

        // These data attributes are the explicit contract between the Blade
        // template, the inline deadline script, and resources/js/app.js.
        this.status = page.locator('[data-clerk-session-handoff]');
        this.error = page.locator('[data-clerk-handoff-error]');
        this.errorTitle = page.locator('[data-clerk-handoff-error-title]');
        this.returnToSignIn = this.error.getByRole('link', { name: 'Return to sign in' });
        this.retry = this.error.getByRole('button', { name: 'Try again' });
    }

    /**
     * @param {string} [redirectPath] A local destination to resume after auth.
     * @param {import('@playwright/test').PageGotoOptions} [options]
     */
    async goto(redirectPath, options = {}) {
        const query = redirectPath === undefined
            ? ''
            : `?${new URLSearchParams({ redirect: redirectPath }).toString()}`;

        return super.goto(`${AuthHandoffPage.path}${query}`, options);
    }
}
