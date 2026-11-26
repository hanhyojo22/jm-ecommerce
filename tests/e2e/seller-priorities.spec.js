import { expect, test } from '@playwright/test';
import { openAsRole } from './support/role-page.js';

async function openSeller(browser, viewport = { width: 1440, height: 900 }) {
    const session = await openAsRole(browser, 'seller', { viewport });
    await session.context.addCookies([{
        name: 'jmmart_cookie_preferences',
        value: encodeURIComponent(JSON.stringify({ version: 2, functional: false, analytics: false, marketing: false })),
        domain: '127.0.0.1',
        path: '/',
    }]);
    return session;
}

test('seller navigation shows only the control for the current viewport and releases scroll on resize', async ({ browser }) => {
    const { context, page } = await openSeller(browser);
    try {
        await page.goto('/seller');
        const menu = page.getByRole('button', { name: 'Open navigation menu', exact: true });
        const collapse = page.getByRole('button', { name: 'Toggle Seller Center sidebar', exact: true });
        await expect(menu).toBeHidden();
        await expect(collapse).toBeVisible();
        await collapse.click();
        await expect(page.locator('body')).not.toHaveClass(/overflow-hidden/);

        await page.setViewportSize({ width: 390, height: 844 });
        await expect(collapse).toBeHidden();
        await expect(menu).toBeVisible();
        await menu.click();
        await expect(page.getByRole('dialog', { name: 'Seller Center navigation' })).toBeVisible();
        await expect(page.locator('body')).toHaveClass(/overflow-hidden/);
        await page.keyboard.press('Escape');
        await expect(page.getByRole('dialog', { name: 'Seller Center navigation' })).toBeHidden();
        await expect(menu).toBeFocused();
        await menu.click();
        await page.setViewportSize({ width: 1440, height: 900 });
        await expect(page.getByRole('dialog', { name: 'Seller Center navigation' })).toBeHidden();
        await expect(page.locator('body')).not.toHaveClass(/overflow-hidden/);
    } finally {
        await context.close();
    }
});

test('mobile order details keep the reference and payment together without a blank header', async ({ browser }) => {
    const { context, page } = await openSeller(browser, { width: 390, height: 844 });
    try {
        await page.goto('/seller/orders');
        const main = page.locator('.sc-order-head-main');
        await expect(main).toBeVisible();
        expect((await main.boundingBox()).height).toBeLessThan(130);
        expect((await page.locator('.sc-premium-head.sc-order-head').boundingBox()).height).toBeLessThan(260);
    } finally {
        await context.close();
    }
});

test('overview puts orders before analytics and makes every daily sale available with the keyboard', async ({ browser }) => {
    const { context, page } = await openSeller(browser, { width: 390, height: 844 });
    try {
        await page.goto('/seller');
        const orders = page.locator('[data-dashboard-section="orders"]');
        const sales = page.getByRole('region', { name: 'Sales overview' });
        expect((await orders.boundingBox()).y).toBeLessThan((await sales.boundingBox()).y);
        const attention = page.getByRole('region', { name: 'Needs your attention' });
        if (await attention.count()) {
            expect((await attention.boundingBox()).y).toBeLessThan((await orders.boundingBox()).y);
        }
        const summary = page.getByText('View daily sales', { exact: true });
        await summary.focus();
        await page.keyboard.press('Enter');
        await expect(page.getByRole('table', { name: 'Daily store sales in Philippine pesos' })).toBeVisible();
        await expect(page.locator('.sc-sales-data tbody tr')).toHaveCount(30);
        expect((await page.locator('.seller-sales-chart').boundingBox()).height).toBeLessThanOrEqual(144);
    } finally {
        await context.close();
    }
});

test('light and dark zero counters retain readable contrast', async ({ browser }) => {
    const { context, page } = await openSeller(browser);
    try {
        await page.goto('/seller');
        for (const theme of ['light', 'dark']) {
            await page.evaluate(theme => document.documentElement.setAttribute('data-seller-theme', theme), theme);
            const ratios = await page.locator('.sc-order-queue .is-zero strong').evaluateAll(elements => {
                const rgb = value => value.match(/[\d.]+/g).slice(0, 3).map(Number);
                const luminance = values => values.map(value => {
                    const c = value / 255;
                    return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
                }).reduce((sum, value, index) => sum + value * [0.2126, 0.7152, 0.0722][index], 0);
                return elements.map(element => {
                    const fg = luminance(rgb(getComputedStyle(element).color));
                    let ancestor = element;
                    while (ancestor && getComputedStyle(ancestor).backgroundColor === 'rgba(0, 0, 0, 0)') ancestor = ancestor.parentElement;
                    const bg = luminance(rgb(getComputedStyle(ancestor).backgroundColor));
                    return (Math.max(fg, bg) + 0.05) / (Math.min(fg, bg) + 0.05);
                });
            });
            expect(ratios.length).toBeGreaterThan(0);
            for (const ratio of ratios) expect(ratio).toBeGreaterThanOrEqual(4.5);
        }
    } finally {
        await context.close();
    }
});

test('bulk product selection counts unique listings across responsive views and preserves confirmation', async ({ browser }) => {
    const { context, page } = await openSeller(browser);
    try {
        await page.goto('/seller/products');
        const action = page.getByRole('button', { name: 'Unlist selected', exact: true });
        await expect(action).toBeDisabled();
        const first = page.locator('input[name="product_ids[]"]:visible').first();
        await first.check();
        await expect(action).toBeEnabled();
        await expect(page.getByRole('status').filter({ hasText: '1 selected' })).toBeVisible();
        const selectAll = page.locator('input[aria-label="Select all published listings on this page"]:visible');
        await expect(selectAll).toHaveJSProperty('indeterminate', true);
        await selectAll.check();
        const eligible = await page.locator('input[name="product_ids[]"]:visible').count();
        await expect(page.getByRole('status').filter({ hasText: `${eligible} selected` })).toBeVisible();

        await page.setViewportSize({ width: 390, height: 844 });
        await expect(page.locator('input[aria-label="Select all published listings on this page"]:visible')).toBeChecked();
        await expect(page.locator('input[name="product_ids[]"]:visible:checked')).toHaveCount(eligible);
        const confirmation = page.waitForEvent('dialog');
        const click = action.click();
        const dialog = await confirmation;
        expect(dialog.type()).toBe('confirm');
        await dialog.dismiss();
        await click;
        await expect(action).toBeEnabled();
        await page.locator('input[aria-label="Select all published listings on this page"]:visible').uncheck();
        await expect(action).toBeDisabled();

        await page.getByRole('searchbox', { name: 'Search products', exact: true }).fill('no-matching-priority-test-product');
        await page.getByRole('searchbox', { name: 'Search products', exact: true }).press('Enter');
        await expect(page.getByRole('heading', { name: 'No products match this view' })).toBeVisible();
        await expect(action).toBeDisabled();
    } finally {
        await context.close();
    }
});
