import { expect, test } from '@playwright/test';

/*
 * The hand-off page is the one page a visitor reaches with JavaScript still to run: the spinner
 * can only be replaced by the bundle minting a session token and posting it to the server. When
 * any link in that chain stalls, the deadline the page ships itself is the only thing left that
 * can say so — these tests hold it to that.
 */
test('a hand-off that never finishes explains itself instead of spinning forever', async ({ page }) => {
    // Nothing from the bundle arrives, so the deadline in the page has to act on its own.
    await page.route('**/*.js', (route) => route.abort());
    await page.clock.install();

    await page.goto('/auth/refresh?redirect=%2Fcart');

    const handoff = page.locator('[data-clerk-session-handoff]');
    const error = page.locator('[data-clerk-handoff-error]');

    await expect(handoff).toBeVisible();
    await expect(error).toBeHidden();

    await page.clock.fastForward(20_000);

    await expect(error).toBeVisible();
    await expect(handoff).toBeHidden();
    await expect(error).toContainText('We could not finish signing you in.');
    await expect(error.getByRole('link', { name: 'Return to sign in' })).toHaveAttribute('href', /\/sign-in\?redirect=%2Fcart/);
    await expect(error.getByRole('button', { name: 'Try again' })).toBeVisible();
});

test('a hand-off with the bundle running reports missing Clerk settings the same way', async ({ page }) => {
    await page.goto('/auth/complete');

    const error = page.locator('[data-clerk-handoff-error]');

    await expect(error).toBeVisible();
    await expect(page.locator('[data-clerk-session-handoff]')).toBeHidden();
    await expect(error).toContainText('Authentication settings are unavailable.');
});

test('the bundle shows the way out even when the page script never ran', async ({ page }) => {
    /*
     * The two sides cover for each other: the page's deadline speaks when the bundle never
     * arrives, and the bundle shows the same block without it. Here the page's own state object
     * never exists — a blocked inline script — and the bundle still has to report the failure.
     */
    await page.addInitScript(() => {
        Object.defineProperty(window, 'clerkHandoffState', { get: () => undefined, set: () => {} });
    });

    await page.goto('/auth/complete');

    const error = page.locator('[data-clerk-handoff-error]');

    await expect(error).toBeVisible();
    await expect(page.locator('[data-clerk-session-handoff]')).toBeHidden();
    await expect(error).toContainText('Authentication settings are unavailable.');
});
