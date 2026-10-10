import { expect, test } from '@playwright/test';
import { openAsRole } from './support/role-page.js';

test('seller workspace pages render without horizontal overflow', async ({ browser }, testInfo) => {
    const { context, page } = await openAsRole(browser, 'seller');
    const pages = [
        ['overview', '/seller'],
        ['orders', '/seller/orders'],
        ['products', '/seller/products'],
        ['add-product', '/seller/products/create'],
        ['messages', '/messages'],
        ['reviews', '/seller/reviews'],
        ['earnings', '/seller/earnings'],
        ['storefront', '/stores/qa-northwind-store'],
        ['store-settings', '/seller/store-settings'],
    ];
    const overflows = [];

    try {
        for (const [name, path] of pages) {
            const response = await page.goto(path);
            expect(response.ok(), path).toBeTruthy();
            await expect(page.locator('main').first()).toBeVisible();
            if (name !== 'storefront') {
                await expect(page.locator('header[data-header-role="seller"]')).toBeVisible();
            }
            await page.screenshot({ path: testInfo.outputPath(`${name}.png`) });

            const width = await page.evaluate(() => ({
                viewport: document.documentElement.clientWidth,
                document: document.documentElement.scrollWidth,
            }));
            if (width.document > width.viewport) {
                overflows.push(`${name}: ${width.document}px > ${width.viewport}px`);
            }
        }

        expect(overflows).toEqual([]);

        if (testInfo.project.name === 'desktop-chromium') {
            await page.setViewportSize({ width: 1536, height: 1024 });
            await page.goto('/seller');
            await expect(page.getByRole('heading', { name: 'Sales overview' })).toBeVisible();
            await expect(page.getByRole('heading', { name: 'Quick actions' })).toBeVisible();
            await page.screenshot({ path: testInfo.outputPath('overview-reference-size.png') });
            const sidebar = page.getByRole('complementary', { name: 'Seller Center sidebar' });
            await page.getByRole('button', { name: 'Toggle Seller Center sidebar' }).click();
            await expect(sidebar).toBeHidden();
            await page.getByRole('button', { name: 'Toggle Seller Center sidebar' }).click();
            await expect(sidebar).toBeVisible();
        }

        if (testInfo.project.name !== 'desktop-chromium') {
            await page.goto('/seller');
            await page.getByRole('button', { name: 'Open navigation menu' }).click();
            const drawer = page.getByRole('dialog', { name: 'Seller Center navigation' });
            await expect(drawer).toBeVisible();
            await drawer.getByRole('link', { name: 'Reviews' }).click();
            await expect(page).toHaveURL(/\/seller\/reviews$/);
            await expect(page.getByRole('heading', { name: 'Reviews', exact: true })).toBeVisible();
        }
    } finally {
        await context.close();
    }
});

test('seller order detail and product editor remain usable at each viewport', async ({ browser }, testInfo) => {
    test.setTimeout(60_000);
    const customer = await openAsRole(browser, 'customer');

    try {
        await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await customer.page.locator('#purchase').getByRole('button', { name: 'Add to cart' }).click();
        await customer.page.getByRole('link', { name: 'Cart: 1 item' }).click();
        await customer.page.getByRole('link', { name: 'Continue to checkout' }).click();
        await customer.page.getByLabel('Recipient name').fill('Ava QA Customer');
        await customer.page.getByLabel('Mobile number').fill('+639171234567');
        await customer.page.getByLabel('Street address').fill('10 QA Street');
        await customer.page.getByLabel('City').fill('Manila');
        await customer.page.getByRole('button', { name: 'Continue to delivery' }).click();
        await customer.page.getByRole('button', { name: 'Continue to payment' }).click();
        await customer.page.getByRole('button', { name: 'Review order' }).click();
        await customer.page.getByRole('button', { name: /Place order/ }).click();
        await expect(customer.page).toHaveURL(/\/orders\/[0-9A-HJKMNP-TV-Z]+$/i);
    } finally {
        await customer.context.close();
    }

    const seller = await openAsRole(browser, 'seller');

    try {
        for (const [name, path, action] of [
            ['order-detail', '/seller/orders', 'View order'],
            ['edit-product', '/seller/products', 'Edit'],
        ]) {
            await seller.page.goto(path);
            if (name === 'order-detail') {
                const listWidth = await seller.page.evaluate(() => ({
                    viewport: document.documentElement.clientWidth,
                    document: document.documentElement.scrollWidth,
                }));
                expect(listWidth.document, 'populated orders list').toBeLessThanOrEqual(listWidth.viewport);
            }
            await seller.page.getByRole('link', { name: action, exact: true }).first().click();
            await expect(seller.page).toHaveURL(name === 'order-detail' ? /\/seller\/orders\/[^/]+$/ : /\/seller\/products\/[^/]+\/edit$/);
            await expect(seller.page.locator('header[data-header-role="seller"]')).toBeVisible();
            await expect(seller.page.locator('main h1')).toBeVisible();
            const width = await seller.page.evaluate(() => ({
                viewport: document.documentElement.clientWidth,
                document: document.documentElement.scrollWidth,
            }));
            expect(width.document, name).toBeLessThanOrEqual(width.viewport);
            await seller.page.screenshot({ path: testInfo.outputPath(`${name}.png`) });
        }
    } finally {
        await seller.context.close();
    }
});

test('homepage remains usable without page-level horizontal overflow', async ({ page }) => {
    await page.goto('/');

    await expect(page.getByRole('navigation', { name: 'Marketplace sections' })).toBeVisible();
    await expect(page.getByRole('banner').getByRole('link', { name: 'JMMart marketplace home' })).toBeVisible();

    const dimensions = await page.evaluate(() => ({
        viewport: document.documentElement.clientWidth,
        document: document.documentElement.scrollWidth,
    }));

    expect(dimensions.document).toBeLessThanOrEqual(dimensions.viewport);
});

test('customer header stays the same across shopping and account pages', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');

    try {
        const heights = [];

        for (const path of ['/', '/search', '/stores/qa-northwind-store/products/qa-canvas-tote', '/profile', '/orders', '/cart']) {
            await page.goto(path);
            const header = page.locator('header[data-header-design]');

            await expect(header.getByRole('link', { name: 'JMMart marketplace home' })).toBeVisible();
            await expect(header.getByRole('navigation', { name: 'Marketplace sections' })).toBeVisible();
            await expect(header.getByRole('textbox', { name: 'Search products, brands, and stores' })).toBeVisible();
            await expect(page.locator('[data-header-promo]')).toBeVisible();
            heights.push(Math.round((await header.boundingBox()).height));
        }

        expect(new Set(heights).size).toBe(1);
    } finally {
        await context.close();
    }
});
