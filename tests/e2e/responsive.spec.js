import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
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

test('store settings preview edits at each viewport', async ({ browser }, testInfo) => {
    const { context, page } = await openAsRole(browser, 'seller');
    const tinyPng = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL6TAAAAABJRU5ErkJggg==', 'base64');

    try {
        await page.goto('/seller/store-settings');
        await page.getByRole('button', { name: 'Reject Optional' }).click();
        await expect(page.locator('[data-cookie-banner]')).toBeHidden();
        const container = await page.locator('.page-shell > .page-container').boundingBox();
        for (const path of ['/seller/products', '/seller/orders']) {
            await page.goto(path);
            const peerContainer = await page.locator('.page-shell > .page-container').boundingBox();
            expect(peerContainer.x).toBeCloseTo(container.x, 0);
            expect(peerContainer.width).toBeCloseTo(container.width, 0);
        }
        await page.goto('/seller/store-settings');
        const identity = await page.locator('section[aria-labelledby="store-profile-heading"]').boundingBox();
        const contact = await page.locator('section[aria-labelledby="contact-details-heading"]').boundingBox();
        const previewSection = page.locator('section[aria-labelledby="store-preview-heading"]');
        const preview = await previewSection.boundingBox();
        expect(await page.locator('#store-preview-heading').evaluate(element => element.scrollWidth <= element.clientWidth)).toBeTruthy();
        if (testInfo.project.name === 'desktop-chromium') {
            expect(preview.x).toBeGreaterThan(identity.x + identity.width);
            expect(Math.abs(preview.y - identity.y)).toBeLessThan(2);
            expect(identity.width / preview.width).toBeGreaterThan(1.8);
            const accessibility = await new AxeBuilder({ page })
                .include('form[action$="/seller/store-settings"]')
                .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
                .analyze();
            expect(accessibility.violations).toEqual([]);
        } else {
            expect(preview.y).toBeGreaterThan(contact.y + contact.height);
        }
        await expect(page.getByText('All changes saved')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Save store settings' })).toBeDisabled();
        await page.getByLabel('Store description').fill('A local shop.');
        await expect(page.getByText('Unsaved changes', { exact: true })).toBeVisible();
        await expect(previewSection.locator('dd').nth(2)).toHaveText('A local shop.');
        await expect(previewSection.getByText('Previewing unsaved changes.')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Save store settings' })).toBeEnabled();
        await expect(page.locator('#description-count')).toHaveText('13 / 2,000 characters');
        await page.getByLabel('Store description').fill('A local shop. 🛍');
        await expect(page.locator('#description-count')).toHaveText('15 / 2,000 characters');
        await page.getByLabel('Change logo').setInputFiles({ name: 'new-logo.png', mimeType: 'image/png', buffer: tinyPng });
        await expect(page.getByAltText('Selected store logo preview')).toBeVisible();
        await expect(page.getByAltText('Selected storefront logo preview')).toBeVisible();
        await expect(page.getByText('New logo preview')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();
        if (testInfo.project.name === 'mobile-chromium') {
            await page.setViewportSize({ width: 375, height: 812 });
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();
        }
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.screenshot({ path: testInfo.outputPath('store-settings-edit.png'), fullPage: true });
        if (testInfo.project.name === 'desktop-chromium') {
            await page.setViewportSize({ width: 1920, height: 1080 });
            const wideContainer = await page.locator('.page-shell > .page-container').boundingBox();
            const address = await page.getByLabel('Street address').boundingBox();
            expect(wideContainer.width).toBeCloseTo(1500, 0);
            expect(address.width).toBeLessThanOrEqual(768);
            await page.screenshot({ path: testInfo.outputPath('store-settings-wide.png'), fullPage: true });
        }
    } finally {
        await context.close();
    }
});

test('store settings save, logo removal, and validation work in the browser', async ({ browser }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-chromium', 'One end-to-end save flow is sufficient');
    const { context, page } = await openAsRole(browser, 'seller');
    const tinyPng = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL6TAAAAABJRU5ErkJggg==', 'base64');
    let releaseFirstUpdate;
    const firstUpdate = new Promise(resolve => { releaseFirstUpdate = resolve; });

    try {
        await page.goto('/seller/store-settings');
        await page.getByRole('button', { name: 'Reject Optional' }).click();
        await page.getByLabel('Store description').fill('A local shop with useful goods.');
        await page.getByLabel('Phone').fill('+639171234568');
        await page.getByLabel('City').fill('Quezon City');
        await page.getByLabel('Street address').fill('25 QA Street');
        await page.getByLabel('Change logo').setInputFiles({ name: 'new-logo.png', mimeType: 'image/png', buffer: tinyPng });
        const preview = page.locator('section[aria-labelledby="store-preview-heading"]');
        await expect(preview.locator('dd').nth(2)).toHaveText('A local shop with useful goods.');
        await expect(preview.locator('dd').nth(3)).toHaveText('+639171234568');
        await expect(preview.locator('dd').nth(4)).toHaveText('Quezon City');
        await expect(preview.locator('dd').nth(5)).toHaveText('25 QA Street');
        await expect(preview.getByAltText('Selected storefront logo preview')).toBeVisible();

        let updates = 0;
        await page.route('**/seller/store-settings', async route => {
            if (route.request().method() === 'POST') {
                updates++;
                if (updates === 1) {
                    await firstUpdate;
                }
            }
            await route.continue();
        });
        const saveButton = page.getByRole('button', { name: 'Save store settings' });
        await expect(saveButton).toBeEnabled();
        await page.evaluate(() => {
            const form = document.querySelector('form[action$="/seller/store-settings"]');
            form.requestSubmit();
            form.requestSubmit();
        });
        await expect.poll(() => updates).toBe(1);
        releaseFirstUpdate();
        await expect(page.getByText('Store details saved.')).toBeVisible();
        expect(updates).toBe(1);
        await expect(page.getByLabel('Store description')).toHaveValue('A local shop with useful goods.');
        await expect(page.getByLabel('Phone')).toHaveValue('+639171234568');
        await expect(page.getByLabel('City')).toHaveValue('Quezon City');
        await expect(page.getByLabel('Street address')).toHaveValue('25 QA Street');
        await expect(preview.locator('img:visible')).toHaveCount(1);
        await expect(preview.locator('dd').nth(2)).toHaveText('A local shop with useful goods.');
        await expect(preview.locator('dd').nth(3)).toHaveText('+639171234568');
        await expect(preview.locator('dd').nth(4)).toHaveText('Quezon City');
        await expect(preview.locator('dd').nth(5)).toHaveText('25 QA Street');
        await expect(preview.getByText('Previewing unsaved changes.')).toBeHidden();
        await page.reload();
        await expect(preview.locator('dd').nth(2)).toHaveText('A local shop with useful goods.');
        await expect(preview.locator('dd').nth(3)).toHaveText('+639171234568');
        await expect(page.getByRole('button', { name: 'Save store settings' })).toBeDisabled();

        await page.getByLabel('Remove current logo on save').check();
        await expect(page.getByText('Logo will be removed')).toBeVisible();
        await expect(preview.locator('img:visible')).toHaveCount(0);
        await page.getByLabel('Change logo').setInputFiles({ name: 'replacement.png', mimeType: 'image/png', buffer: tinyPng });
        await expect(page.getByLabel('Remove current logo on save')).not.toBeChecked();
        await expect(preview.getByAltText('Selected storefront logo preview')).toBeVisible();
        await page.getByLabel('Remove current logo on save').check();
        await expect(page.getByText('Logo will be removed')).toBeVisible();
        await page.getByRole('button', { name: 'Save store settings' }).click();
        await expect(page.getByText('Store details saved.')).toBeVisible();
        await expect(page.getByLabel('Remove current logo on save')).toHaveCount(0);
        await expect(preview.locator('img:visible')).toHaveCount(0);

        await page.getByLabel('Store description').fill('This failed save should stay unsaved.');
        await page.getByLabel('Change logo').setInputFiles({ name: 'invalid.txt', mimeType: 'text/plain', buffer: Buffer.from('not an image') });
        await page.getByRole('button', { name: 'Save store settings' }).click();
        await expect(page.getByRole('alert')).toContainText('We could not save your changes');
        await expect(page.locator('#logo-error')).toBeVisible();
        await expect(preview.locator('dd').nth(2)).toHaveText('This failed save should stay unsaved.');
        await expect(preview.getByText('Previewing unsaved changes.')).toBeVisible();
    } finally {
        releaseFirstUpdate?.();
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
