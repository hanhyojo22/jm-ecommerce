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

test('removing a cart item persists across a reload', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');
    try {
        await addFirstProductToCart(page);
        const remove = page.getByRole('button', { name: /^Remove .+ from your cart$/ }).first();
        const label = await remove.getAttribute('aria-label');
        await remove.click();
        await expect(page.getByRole('button', { name: label, exact: true })).toHaveCount(0);
        await page.reload();
        await expect(page.getByRole('button', { name: label, exact: true })).toHaveCount(0);
    } finally { await context.close(); }
});

test('a lapsed session is refreshed before removing a cart item', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');
    try {
        await addFirstProductToCart(page);
        await context.clearCookies({ name: '__session' });
        let renewals = 0;
        await page.route('**/auth/session', async route => { renewals++; await route.continue(); });
        const remove = page.getByRole('button', {name: /^Remove .+ from your cart$/}).first();
        const label = await remove.getAttribute('aria-label');
        await remove.click();
        await expect(page.getByRole('button', {name: label, exact: true})).toHaveCount(0);
        expect(renewals).toBeGreaterThan(0);
        await expect(page.getByText('Your sign-in expired')).toHaveCount(0);
    } finally { await context.close(); }
});

test('a quantity change shows it is busy while the session renews and still submits its own value', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');
    let releaseSession;
    const heldSession = new Promise((resolve) => { releaseSession = resolve; });

    try {
        await addFirstProductToCart(page);

        const increase = page.getByRole('button', { name: 'Increase quantity' }).first();
        const form = page.locator('form').filter({ has: increase }).first();
        const startingQuantity = Number(await form.locator('span').first().innerText());

        // Force the renewal the click has to wait for, and hold it open.
        await context.clearCookies({ name: '__session' });
        await page.route('**/auth/session', async (route) => {
            await heldSession;
            await route.continue();
        });

        await increase.click();
        await expect(form).toHaveAttribute('aria-busy', 'true');
        await expect(increase).toBeDisabled();

        releaseSession();
        await page.waitForLoadState('domcontentloaded');
        await expect(page.getByRole('button', { name: 'Increase quantity' }).first()).toBeEnabled();

        // The new quantity rides on the button's own name and value, so it survives the wait.
        const quantities = await page.locator('form:has(button[aria-label="Increase quantity"]) span').first().innerText();
        expect(Number(quantities)).toBe(startingQuantity + 1);
    } finally {
        releaseSession();
        await context.close();
    }
});

test('the cart badge holds still for a visitor who asked for reduced motion', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer', { reducedMotion: 'reduce' });

    try {
        await page.goto('/search');
        await page.locator('a[href*="/products/"]').first().click();
        const badge = page.locator('[data-cart-link] [data-cart-badge]');
        const before = await page.locator('[data-cart-link]').getAttribute('aria-label');

        await page.getByRole('button', { name: /add to cart/i }).first().click();

        // The count still has to land; only the beat is dropped.
        await expect(page.locator('[data-cart-link]')).not.toHaveAttribute('aria-label', before);
        expect(await badge.evaluate((node) => node.getAnimations().length)).toBe(0);
    } finally {
        await context.close();
    }
});

test('the cart icon stays legible behind the widest count', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer', { viewport: { width: 390, height: 800 } });

    try {
        await page.goto('/');

        // The badge grows with the count, so the widest value it can show is the one that has to fit.
        const covered = await page.locator('[data-cart-badge]').evaluate((badge) => {
            badge.style.display = '';
            badge.textContent = '99+';
            const link = badge.closest('[data-cart-link]');
            const b = badge.getBoundingClientRect();
            const icon = link.querySelector('svg').getBoundingClientRect();
            const overlapX = Math.max(0, Math.min(b.right, icon.right) - Math.max(b.left, icon.left));
            const overlapY = Math.max(0, Math.min(b.bottom, icon.bottom) - Math.max(b.top, icon.top));

            return (overlapX * overlapY) / (icon.width * icon.height);
        });

        expect(covered).toBeLessThan(0.25);
    } finally {
        await context.close();
    }
});

test('the cart proceeds to checkout without item selection controls', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');
    try {
        await addFirstProductToCart(page);
        await expect(page.locator('input[id^="select-"]')).toHaveCount(0);
        await page.getByRole('link', { name: /^Check out(?:\s*\(\s*\d+\s*\))?$/ }).click();
        await expect(page).toHaveURL(/\/checkout$/);
        await expect(page.getByRole('heading', { name: 'Checkout', exact: true })).toBeVisible();
    } finally { await context.close(); }
});
