import { expect, test } from '@playwright/test';
import { openAsRole } from './support/role-page.js';

test('seller application steps remain usable on mobile and desktop', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'responsiveApplicant');

    try {
        await page.goto('/start-selling');
        await expect(page.getByRole('heading', { name: 'Become a seller' })).toBeVisible();
        await expect(page.getByRole('navigation', { name: 'Application progress' }).getByRole('link')).toHaveCount(7);
        await page.getByRole('link', { name: /Step 4 Address and fulfillment/ }).click();
        await expect(page.getByLabel('Region')).toBeVisible();
        await expect(page.getByLabel('Return address')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();
    } finally {
        await context.close();
    }
});

test('catalog filters stay out of the mobile product flow until opened', async ({ page }, testInfo) => {
    await page.goto('/search');
    const panel = page.locator('#catalog-filters');
    const button = page.locator('#product-results button[aria-controls="catalog-filters"]').last();

    if (testInfo.project.name === 'desktop-chromium') {
        await expect(panel).toBeVisible();
        await expect(button).toBeHidden();
    } else {
        await expect(panel).toBeHidden();
        await expect(button).toBeVisible();
        await button.click();
        await expect(panel).toBeVisible();
        await panel.getByRole('button', { name: /close filters/i }).click();
        await expect(panel).toBeHidden();
    }
});

test('mobile product title and price appear before the gallery', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'desktop-chromium', 'Mobile layout only');
    await page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
    const title = page.getByRole('heading', { name: 'QA Canvas Tote', level: 1 });
    const gallery = page.getByRole('region', { name: 'Product images' });
    await expect(title).toBeVisible();
    const titleBox = await title.boundingBox();
    const galleryBox = await gallery.boundingBox();
    expect(titleBox.y + titleBox.height).toBeLessThan(galleryBox.y);
});

test('seller adds color and size options in the product form at each viewport', async ({ browser }, testInfo) => {
    const { context, page } = await openAsRole(browser, 'seller');

    try {
        await page.goto('/seller/products/create');
        await page.getByRole('button', { name: 'Reject Optional' }).click();
        await expect(page.locator('[data-cookie-banner]')).toBeHidden();
        await page.reload();
        await expect(page.getByRole('heading', { name: 'Color and size options' })).toBeVisible();
        await page.locator('#name').fill('Variant QA Shirt');
        await page.locator('#sku').fill(`VARIANT-QA-${testInfo.project.name}`);
        await page.locator('#price').fill('199.00');
        await page.getByRole('button', { name: 'Add option' }).click();
        await page.locator('input[name="variants[0][color]"]').fill('Navy');
        await page.locator('input[name="variants[0][size]"]').fill('Medium');
        await page.locator('input[name="variants[0][sku]"]').fill(`VARIANT-QA-N-M-${testInfo.project.name}`);
        await page.locator('input[name="variants[0][stock_quantity]"]').fill('3');
        await expect(page.locator('#stock_quantity')).toBeDisabled();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();
        await page.screenshot({ path: testInfo.outputPath('product-variant-form.png'), fullPage: true });
        await page.getByRole('button', { name: 'Save product' }).click();
        await expect(page).toHaveURL(/\/seller\/products$/);
        await expect(page.getByText('Variant QA Shirt').first()).toBeVisible();
    } finally {
        await context.close();
    }
});

test('guest Sign In stays accessible without header overflow at each viewport', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('header').getByRole('link', { name: 'Sign In', exact: true })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();
});

test('seller switches workspaces from the account dropdown at each viewport', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'seller');

    try {
        await page.goto('/seller');
        await page.getByRole('button', { name: 'Account menu' }).click();
        const menu = page.locator('#header-account-menu');
        await expect(menu.locator('[data-workspace-switch]')).toHaveCount(1);
        if (await menu.locator('[data-workspace-switch] input[name="workspace"]').inputValue() === 'seller') {
            await menu.getByRole('button', { name: 'Switch Account' }).click();
            await page.goto('/seller');
            await page.getByRole('button', { name: 'Account menu' }).click();
        }
        await expect(menu.locator('[data-workspace-switch] input[name="workspace"]')).toHaveValue('customer');
        await menu.getByRole('button', { name: 'Switch Account' }).click();
        await expect(page).toHaveURL(/https?:\/\/[^/]+\/$/);
        await expect(page.locator('header[data-header-role="customer"]')).toBeVisible();

        await page.getByRole('button', { name: 'Account menu' }).click();
        await expect(menu.locator('[data-workspace-switch] input[name="workspace"]')).toHaveValue('seller');
        await menu.getByRole('button', { name: 'Switch Account' }).click();
        await expect(page).toHaveURL(/\/seller$/);
        await expect(page.locator('header[data-header-role="seller"]')).toBeVisible();
    } finally {
        await context.close();
    }
});

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
        const customerCookieBanner = customer.page.locator('[data-cookie-banner]');
        if (await customerCookieBanner.isVisible()) {
            await customerCookieBanner.locator('[data-cookie-action="reject"]').click();
        }
        const cartForm = customer.page.locator('#purchase form');
        const cartResponse = await customer.context.request.post(await cartForm.getAttribute('action'), {
            form: { _token: await cartForm.locator('input[name="_token"]').inputValue(), quantity: '1' },
            headers: { Accept: 'application/json' },
        });
        expect(cartResponse.ok()).toBeTruthy();
        await customer.page.goto('/cart');
        await customer.page.getByRole('link', { name: 'Continue to checkout' }).click();
        await customer.page.getByLabel('Recipient name').fill('Ava QA Customer');
        await customer.page.getByLabel('Mobile number').fill('+639171234567');
        await customer.page.getByLabel('Street address').fill('10 QA Street');
        await customer.page.getByLabel('City').fill('Manila');
        await customer.page.getByRole('button', { name: 'Review order' }).click();
        await expect(customer.page.getByText('10 QA Street, Manila', { exact: true })).toBeVisible();
        await customer.page.getByRole('button', { name: /Place order/ }).click();
        await expect(customer.page).toHaveURL(/\/orders\/[0-9A-HJKMNP-TV-Z]+$/i);
    } finally {
        await customer.context.close();
    }

    const seller = await openAsRole(browser, 'seller');

    try {
        for (const [name, path, action] of [
            ['order-detail', '/seller/orders', 'View order'],
            ['edit-product', '/seller/products', 'Edit listing'],
        ]) {
            await seller.page.goto(path);
            const sellerCookieBanner = seller.page.locator('[data-cookie-banner]');
            if (await sellerCookieBanner.isVisible()) {
                await sellerCookieBanner.locator('[data-cookie-action="reject"]').click();
            }
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

test('customer header uses the intended shopping and account layouts', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');

    try {
        const shoppingHeights = [];
        const accountHeights = [];

        for (const path of ['/', '/search', '/stores/qa-northwind-store/products/qa-canvas-tote', '/profile', '/orders', '/cart']) {
            await page.goto(path);
            const header = page.locator('header[data-header-design]');
            const isAccountPage = ['/profile', '/orders'].includes(path);

            await expect(header.getByRole('textbox', { name: 'Search products, brands, and stores' })).toBeVisible();
            if (isAccountPage) {
                await expect(header).toHaveAttribute('data-header-role', 'customer-workspace');
                await expect(header.getByRole('navigation', { name: 'Marketplace sections' })).toHaveCount(0);
                await expect(page.locator('[data-header-promo]')).toHaveCount(0);
                accountHeights.push(Math.round((await header.boundingBox()).height));
            } else {
                await expect(header.getByRole('link', { name: 'JMMart marketplace home' })).toBeVisible();
                await expect(header.getByRole('navigation', { name: 'Marketplace sections' })).toBeVisible();
                await expect(page.locator('[data-header-promo]')).toBeVisible();
                shoppingHeights.push(Math.round((await header.boundingBox()).height));
            }
        }

        expect(new Set(shoppingHeights).size).toBe(1);
        expect(new Set(accountHeights).size).toBe(1);
    } finally {
        await context.close();
    }
});
