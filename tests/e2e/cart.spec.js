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

        // Start from a known ticked state. Adding ticks the item already, so only tick it here
        // when it somehow arrived unticked — check() on a ticked box sends no request to wait for.
        const checkbox = page.locator('input[type="checkbox"][id^="select-"]').first();
        if (!(await checkbox.isChecked())) {
            await checkbox.check();
            await page.waitForResponse((response) => response.url().includes('/cart/selection'));
        }

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

test('the checkout link stays usable while a tick is still saving', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');
    let releaseSave;
    const heldSave = new Promise((resolve) => { releaseSave = resolve; });

    try {
        await addFirstProductToCart(page);

        const checkbox = page.locator('input[type="checkbox"][id^="select-"]').first();
        const checkout = page.getByRole('link', { name: /^Check out(?:\s*\(\s*\d+\s*\))?$/ });

        // Start unticked so the tick below is what sends the save being held open.
        if (await checkbox.isChecked()) {
            await checkbox.uncheck();
            await page.waitForResponse((response) => response.url().includes('/cart/selection'));
        }

        await page.route('**/cart/selection', async (route) => {
            await heldSave;
            await route.continue();
        });

        await checkbox.check();

        // The save is still in flight: the control the buyer is reaching for must stay live.
        await expect(checkout).toHaveAttribute('aria-disabled', 'false');

        // Checkout reads the ticked items from the server, so the click waits for that save.
        const navigated = page.waitForURL(/\/checkout$/);
        await checkout.click();
        releaseSave();
        await navigated;
    } finally {
        releaseSave();
        await context.close();
    }
});
