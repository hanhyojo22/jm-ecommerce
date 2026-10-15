import { expect, test } from '@playwright/test';
import { openAsRole } from './support/role-page.js';

const addFirstProductToCart = async (page) => {
    await page.goto('/search');
    await page.locator('a[href*="/products/"]').first().click();
    // Adding navigates on its own, so let that settle before asking for the cart.
    await page.getByRole('button', { name: /add to cart/i }).first().click();
    await page.waitForLoadState('networkidle');

    // The app refreshes the Clerk session on navigation and assigns the URL
    // itself, which aborts a goto issued at the same moment. Retry past it.
    for (let attempt = 0; attempt < 4; attempt += 1) {
        try {
            await page.goto('/cart', { waitUntil: 'domcontentloaded' });
            break;
        } catch {
            await page.waitForTimeout(1500);
        }
    }

    await expect(page.getByRole('heading', { name: 'Shopping cart' })).toBeVisible();
};

test('unticking a cart item persists across a reload', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');

    try {
        await addFirstProductToCart(page);

        const checkbox = page.locator('input[type="checkbox"][id^="select-"]').first();
        await expect(checkbox).toBeChecked();

        await checkbox.uncheck();
        await expect(page.getByText('0 of 1 selected')).toBeVisible();
        await page.waitForResponse((response) => response.url().includes('/cart/selection'));

        await page.reload();
        await expect(page.locator('input[type="checkbox"][id^="select-"]').first()).not.toBeChecked();
    } finally {
        await context.close();
    }
});

/**
 * The Clerk-backed session lives about a minute, so a buyer who leaves the
 * cart open and then ticks a box must not lose the change to one stale
 * response. The request is retried once after a session refresh.
 */
test('a lapsed session is retried instead of shown as an error', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');

    try {
        await addFirstProductToCart(page);

        // Start from a known ticked state; an earlier test shares this cart.
        const checkbox = page.locator('input[type="checkbox"][id^="select-"]').first();
        await checkbox.check();
        await page.waitForResponse((response) => response.url().includes('/cart/selection'));

        let attempts = 0;
        await page.route('**/cart/selection', async (route) => {
            attempts += 1;

            if (attempts === 1) {
                await route.fulfill({ status: 401, contentType: 'application/json', body: '{}' });

                return;
            }

            await route.continue();
        });

        await checkbox.uncheck();
        await expect.poll(() => attempts).toBe(2);
        await expect(page.getByText('Your sign-in expired')).toBeHidden();

        await page.reload();
        await expect(page.locator('input[type="checkbox"][id^="select-"]').first()).not.toBeChecked();
    } finally {
        await context.close();
    }
});
