import { expect, test } from '@playwright/test';
import { AuthHandoffPage } from './pages/auth-handoff.page.js';
import { CartPage } from './pages/cart.page.js';
import { SignInPage } from './pages/sign-in.page.js';
import {
    authCopy,
    authRoutes,
    authUsers,
    injectionRedirect,
    invalidRedirects,
} from './fixtures/auth.data.js';
import { openAsRole } from './support/role-page.js';
import { enableClerkTestHarness } from './support/clerk-test-harness.js';

// These scenarios only read authentication state and use per-test contexts, so
// they are safe to shard. The rest of the seeded suite keeps its serial default.
test.describe.configure({ mode: 'parallel' });

test.describe('Authentication entry points', () => {
    /*
     * Scenario A — positive main journey.
     *
     * The real credential UI belongs to Clerk, so the browser-level contract we
     * own is the same-origin handoff: exchange a verified Clerk token for the
     * Laravel session cookie, then resume the requested page. The harness
     * supplies a locally accepted token, but the production script, POST,
     * cookie, and redirect all run unchanged.
     */
    test('A. a verified buyer completes the sign-in handoff and lands on the requested page', async ({ browser }) => {
        const { context, page } = await openAsRole(browser, authUsers.buyer.role);

        try {
            await enableClerkTestHarness(page);

            const handoff = new AuthHandoffPage(page);
            const completion = page.waitForResponse((response) =>
                response.request().method() === 'POST'
                && new URL(response.url()).pathname === authRoutes.handoff,
            );

            await handoff.goto(authRoutes.cart);

            await expect(page).toHaveURL(new RegExp(`${authRoutes.cart}$`));

            const completionResponse = await completion;
            expect(completionResponse.status()).toBe(200);
            expect(completionResponse.headers()['content-type']).toContain('application/json');

            const cart = new CartPage(page);
            await expect(cart.heading).toBeVisible();
            await expect(cart.accountMenu).toBeVisible();

            const sessionCookie = (await context.cookies()).find((cookie) => cookie.name === '__session');
            expect(sessionCookie?.value).toBe(authUsers.buyer.sessionToken);
        } finally {
            await context.close();
        }
    });

    /*
     * Scenario B — negative and malformed input.
     *
     * A missing redirect is a normal sign-in visit. A malformed redirect must be
     * refused before Clerk is mounted, and an injection-shaped string that is a
     * syntactically valid local path must remain inert text. This prevents both
     * open redirects and reflected database errors.
     */
    test('B. missing or malicious redirect input is handled without an open redirect or server error', async ({ page }) => {
        const signIn = new SignInPage(page);

        const directVisit = await signIn.goto();
        expect(directVisit?.status()).toBe(200);
        await expect(signIn.heading).toHaveText(authCopy.signInHeading);

        const emptyRedirect = await signIn.goto('');
        expect(emptyRedirect?.status()).toBe(200);
        await expect(signIn.heading).toHaveText(authCopy.signInHeading);

        const injection = await signIn.goto(injectionRedirect);
        expect(injection?.status()).toBe(200);
        await expect(signIn.heading).toHaveText(authCopy.signInHeading);
        await expect(page.locator('body')).not.toContainText('SQLSTATE');

        // The E2E environment has no Clerk key, so the page must explain the
        // recoverable state instead of leaving an empty mount point.
        await expect(signIn.authError).toBeVisible();
        await expect(signIn.refreshButton).toBeVisible();

        for (const invalid of invalidRedirects) {
            const response = await signIn.goto(invalid.value);
            expect(response?.status(), `${invalid.label} should be rejected`).toBe(422);
            expect(new URL(page.url()).pathname, `${invalid.label} must not navigate away`).toBe(authRoutes.signIn);
        }
    });

    /*
     * Scenario C — edge case / unreliable network.
     *
     * A handoff is a browser fetch. If the server cannot be reached, the visitor
     * must get an actionable error and a way back, not a spinner that never
     * resolves. The network failure is scoped to the POST so the page itself
     * still loads.
     */
    test('C. an unreachable handoff endpoint fails visibly instead of spinning forever', async ({ browser }) => {
        const { context, page } = await openAsRole(browser, authUsers.buyer.role);

        try {
            await enableClerkTestHarness(page, { failSessionRequest: true });

            const handoff = new AuthHandoffPage(page);
            await handoff.goto(authRoutes.cart);

            await expect(handoff.error).toBeVisible();
            await expect(handoff.errorTitle).toHaveText(authCopy.handoffErrorTitle);
            await expect(handoff.status).toBeHidden();
            await expect(handoff.returnToSignIn).toHaveAttribute('href', /\/sign-in\?redirect=%2Fcart/);
            await expect(handoff.retry).toBeVisible();
            await expect(page).toHaveURL(/\/auth\/complete\?redirect=%2Fcart$/);
        } finally {
            await context.close();
        }
    });

    /*
     * Scenario D — state transition.
     *
     * Changing modes is a navigation, so the selected tab, heading, and mounted
     * Clerk component must all change together. This catches stale aria state
     * and the classic bug where only the visible heading updates.
     */
    test('D. switching auth modes keeps the selected tab, heading, and component in sync', async ({ page }) => {
        const signIn = new SignInPage(page);

        await signIn.goto(authRoutes.cart);

        await expect(signIn.signInTab).toHaveAttribute('aria-selected', 'true');
        await expect(signIn.createAccountTab).not.toHaveAttribute('aria-selected', 'true');
        await expect(signIn.heading).toHaveText(authCopy.signInHeading);
        await expect(signIn.authPanel).toHaveAttribute('data-clerk-component', 'sign-in');

        await signIn.openCreateAccount();

        await expect(page).toHaveURL(/\/sign-up\?redirect=%2Fcart$/);
        await expect(signIn.createAccountTab).toHaveAttribute('aria-selected', 'true');
        await expect(signIn.signInTab).not.toHaveAttribute('aria-selected', 'true');
        await expect(signIn.heading).toHaveText(authCopy.signUpHeading);
        await expect(signIn.authPanel).toHaveAttribute('data-clerk-component', 'sign-up');

        await signIn.openSignIn();

        await expect(page).toHaveURL(/\/sign-in\?redirect=%2Fcart$/);
        await expect(signIn.signInTab).toHaveAttribute('aria-selected', 'true');
        await expect(signIn.createAccountTab).not.toHaveAttribute('aria-selected', 'true');
        await expect(signIn.heading).toHaveText(authCopy.signInHeading);
        await expect(signIn.authPanel).toHaveAttribute('data-clerk-component', 'sign-in');
    });
});
