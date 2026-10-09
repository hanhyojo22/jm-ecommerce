import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { openAsRole } from './support/role-page.js';

test('guest can browse a product and is prompted to sign in before adding it to cart', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByRole('navigation', { name: 'Marketplace sections' })).toBeVisible();

    await page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
    await expect(page.getByRole('heading', { name: 'QA Canvas Tote', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Sign in to add to cart' })).toHaveAttribute('href', /sign-in/);
});

test('customer can place a COD order from the product page through checkout', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');

    try {
        await page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await page.locator('#purchase').getByRole('button', { name: 'Add to cart' }).click();
        await expect(page).toHaveURL(/\/cart$/);
        await page.getByRole('link', { name: 'Continue to checkout' }).click();
        await page.getByLabel('Recipient name').fill('Ava QA Customer');
        await page.getByLabel('Mobile number').fill('+639171234567');
        await page.getByLabel('Street address').fill('10 QA Street');
        await page.getByLabel('City').fill('Manila');
        await page.getByRole('button', { name: 'Continue to delivery' }).click();
        await page.getByRole('button', { name: 'Continue to payment' }).click();
        await expect(page.getByText('Cash on delivery', { exact: true })).toBeVisible();
        await page.getByRole('button', { name: 'Review order' }).click();
        await page.getByRole('button', { name: /Place order/ }).click();

        await expect(page).toHaveURL(/\/orders\/[0-9A-HJKMNP-TV-Z]+$/i);
        await expect(page.getByRole('heading', { name: 'Order details' })).toBeVisible();
        await expect(page.getByText('QA Canvas Tote')).toBeVisible();
        await expect(page.getByText(/Cash on delivery/i)).toBeVisible();
    } finally {
        await context.close();
    }
});

test('customer refreshes the Clerk session before adding an item to the cart', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');

    try {
        await page.route('**/cart/products/**', async (route) => {
            const headers = { ...route.request().headers() };
            delete headers['x-playwright-auth-key'];
            delete headers['x-playwright-identity'];
            await route.continue({ headers });
        });
        await page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        const addToCartForm = page.locator('#purchase form');
        await expect(addToCartForm).toHaveAttribute('data-clerk-session-refresh', /auth\/session/);
        await expect(addToCartForm).toHaveAttribute('action', /cart\/products\//);
        const refresh = page.waitForResponse((response) => response.url().endsWith('/auth/session') && response.request().method() === 'POST');
        await page.locator('#purchase').getByRole('button', { name: 'Add to cart' }).click();
        await expect((await refresh).ok()).toBeTruthy();

        await expect(page).toHaveURL(/\/cart$/);
        await expect(page.getByText('QA Canvas Tote')).toBeVisible();
    } finally {
        await context.close();
    }
});

test('signed-in customer refreshes the Clerk token before opening messages', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');

    try {
        await page.goto('/');
        const refresh = page.waitForResponse((response) => response.url().endsWith('/auth/session') && response.request().method() === 'POST');
        // No opt-in attribute here: navigation renews the session for any link it is about to open.
        await page.getByRole('link', { name: /Messages/ }).first().click();

        await expect((await refresh).ok()).toBeTruthy();
        await expect(page).toHaveURL(/\/messages$/);
        await expect(page.getByRole('heading', { name: 'Messages', exact: true })).toBeVisible();
    } finally {
        await context.close();
    }
});

test('public product page has no automated WCAG A or AA violations', async ({ page }) => {
    await page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .analyze();

    expect(results.violations).toEqual([]);
});

test('marketplace document responds within the local performance budget', async ({ page }) => {
    const response = await page.goto('/', { waitUntil: 'domcontentloaded' });

    expect(response?.ok()).toBe(true);
    const domContentLoaded = await page.evaluate(() => performance.getEntriesByType('navigation')[0]?.domContentLoadedEventEnd ?? Infinity);
    expect(domContentLoaded).toBeLessThan(8_000);
});
