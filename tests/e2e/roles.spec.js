import { expect, test } from '@playwright/test';
import { openAsRole } from './support/role-page.js';

test('seller sees and updates only products from their own store', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'seller');

    try {
        await page.goto('/seller/products');
        await expect(page.getByRole('table').getByText('QA Canvas Tote')).toBeVisible();
        await expect(page.getByRole('table').getByText('₱19.99', { exact: true })).toBeVisible();
        await expect(page.getByText('Other Seller QA Product')).toHaveCount(0);
        await page.getByRole('table').getByRole('link', { name: 'Edit' }).click();
        await page.getByLabel('Stock quantity').fill('24');
        await page.getByRole('button', { name: 'Save changes' }).click();

        await expect(page).toHaveURL(/\/seller\/products$/);
        await expect(page.getByRole('table').getByText('24', { exact: true })).toBeVisible();
    } finally {
        await context.close();
    }
});

test('seller can search dashboard help and use keyboard shortcuts', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'seller');

    try {
        await page.goto('/seller');

        await page.keyboard.press('?');
        const helpSearch = page.getByRole('searchbox', { name: 'Search seller help' });
        await expect(helpSearch).toBeFocused();
        await helpSearch.fill('stock');
        await expect(page.getByRole('link', { name: 'Review out-of-stock products' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'View reviews' })).toBeHidden();
        await expect(page.getByText('No help topics match that search.')).toBeHidden();

        await page.getByRole('heading', { name: 'Overview' }).click();
        await page.keyboard.press('s');
        await expect(page.getByRole('textbox', { name: 'Search your products by name or SKU' })).toBeFocused();

        await page.getByRole('heading', { name: 'Overview' }).click();
        await page.keyboard.press('n');
        await expect(page).toHaveURL(/\/seller\/products\/create$/);

        await page.goto('/seller');
        await page.keyboard.press('o');
        await expect(page).toHaveURL(/\/seller\/orders$/);
    } finally {
        await context.close();
    }
});

test('seller dashboard stays compact and usable on a narrow screen', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'seller');

    try {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/seller');

        await expect(page.getByRole('heading', { name: 'Overview' })).toBeVisible();
        await expect(page.locator('[data-seller-guide]')).toHaveJSProperty('open', false);
        const recentOrders = page.locator('details').filter({ has: page.getByRole('heading', { name: 'Recent orders' }) });
        await expect(recentOrders.locator('summary')).toBeVisible();

        // The panel auto-opens when the seller has pending orders, and the shared fixture
        // database is not reset between tests, so normalise it closed before toggling.
        if (await recentOrders.evaluate((element) => element.open)) {
            await recentOrders.locator('summary').click();
        }

        await expect(recentOrders).toHaveJSProperty('open', false);

        const dimensions = await page.evaluate(() => ({
            viewport: document.documentElement.clientWidth,
            document: document.documentElement.scrollWidth,
        }));

        expect(dimensions.document).toBeLessThanOrEqual(dimensions.viewport);

        await recentOrders.locator('summary').click();
        await expect(recentOrders).toHaveJSProperty('open', true);

        // The fixture database is shared across the run, so this seller may already hold an
        // order from the checkout test. Accept either the empty state or an order card.
        await expect(
            recentOrders.getByText('No orders yet').or(recentOrders.locator('article'))
        ).toBeVisible();
    } finally {
        await context.close();
    }
});

test('seller can persist overview visibility preferences and restore defaults', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'seller');

    try {
        await page.goto('/seller');
        await page.evaluate(() => {
            const dashboard = document.querySelector('[data-seller-dashboard]');
            window.localStorage.setItem(`seller-dashboard:${dashboard.dataset.dashboardKey}`, 'null');
        });
        await page.reload();
        await page.getByText('Customize overview').click();
        await page.getByRole('checkbox', { name: 'Recent products' }).uncheck();
        await expect(page.locator('[data-dashboard-section="products"]')).toBeHidden();

        await page.reload();
        await expect(page.locator('[data-dashboard-section="products"]')).toBeHidden();
        await page.getByText('Customize overview').click();
        await page.getByRole('button', { name: 'Restore defaults' }).click();
        await expect(page.locator('[data-dashboard-section="products"]')).toBeVisible();
    } finally {
        await context.close();
    }
});

test('seller can cancel bulk listing deactivation before any change is submitted', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'seller');

    try {
        await page.goto('/seller/products');
        const selection = page.getByRole('checkbox', { name: 'Select listing QA Canvas Tote' }).first();
        await selection.check();
        page.once('dialog', (dialog) => {
            expect(dialog.type()).toBe('confirm');
            dialog.dismiss();
        });
        await page.getByRole('button', { name: 'Deactivate selected' }).click();
        await expect(page.getByRole('button', { name: 'Deactivate selected' })).toBeVisible();
        await expect(page.getByRole('table').getByText('Active', { exact: true })).toBeVisible();
    } finally {
        await context.close();
    }
});

test('seller product selection fits and remains usable on mobile', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'seller');

    try {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/seller/products');
        await page.getByRole('checkbox', { name: 'Select listing QA Canvas Tote' }).last().check();
        page.once('dialog', (dialog) => dialog.dismiss());
        await page.getByRole('button', { name: 'Deactivate selected' }).click();

        const dimensions = await page.evaluate(() => ({
            viewport: document.documentElement.clientWidth,
            document: document.documentElement.scrollWidth,
        }));

        expect(dimensions.document).toBeLessThanOrEqual(dimensions.viewport);
    } finally {
        await context.close();
    }
});

test('administrator can open the marketplace dashboard and order queue', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'admin');

    try {
        await page.goto('/dashboard');
        await expect(page.getByRole('heading', { name: 'Marketplace overview' })).toBeVisible();
        await page.goto('/admin/orders');
        await expect(page.getByRole('heading', { name: 'Marketplace orders', exact: true })).toBeVisible();
    } finally {
        await context.close();
    }
});

test('customer and admin are refused seller administration and buyer messaging respectively', async ({ browser }) => {
    const customer = await openAsRole(browser, 'customer');
    const admin = await openAsRole(browser, 'admin');

    try {
        const customerResponse = await customer.page.goto('/seller/products');
        const adminResponse = await admin.page.goto('/messages');

        expect(customerResponse?.status()).toBe(403);
        expect(adminResponse?.status()).toBe(403);
    } finally {
        await customer.context.close();
        await admin.context.close();
    }
});

test('guest is redirected to sign in for protected customer resources', async ({ page }) => {
    await page.goto('/cart');

    // A missing or lapsed Clerk session is bridged rather than treated as a sign-out.
    await expect(page).toHaveURL(/\/auth\/refresh\?redirect=%2Fcart/);

    // The bridge grants nothing on its own, so it offers the sign-in page instead.
    await expect(page.getByRole('link', { name: 'Return to sign in' })).toBeVisible();
});

test('seller remains signed in when Seller Center and Account are opened after the session token expires', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'seller');
    const authRefreshRequests = [];

    try {
        page.on('request', (request) => {
            if (new URL(request.url()).pathname === '/auth/refresh') {
                authRefreshRequests.push(request.url());
            }
        });

        await page.goto('/');
        await page.getByRole('button', { name: /Hi,/ }).click();

        const sellerCenterLink = page.locator('#header-account-menu')
            .getByRole('link', { name: 'Seller Center', exact: true });
        await expect(sellerCenterLink).toBeVisible();
        await context.clearCookies({ name: '__session' });
        await context.setExtraHTTPHeaders({});
        const sellerHandoff = page.waitForResponse((response) => response.url().endsWith('/auth/session') && response.request().method() === 'POST');
        await sellerCenterLink.click();
        await expect((await sellerHandoff).ok()).toBeTruthy();

        await expect(page).toHaveURL(/\/seller$/);
        await expect(page.getByRole('heading', { name: 'Overview' })).toBeVisible();
        await expect(page).not.toHaveURL(/\/sign-in|\/auth\//);

        await context.clearCookies({ name: '__session' });
        const accountLink = page.getByRole('complementary', { name: 'Seller Center sidebar' })
            .getByRole('link', { name: 'Account', exact: true });
        const accountHandoff = page.waitForResponse((response) => response.url().endsWith('/auth/session') && response.request().method() === 'POST');
        await accountLink.click();
        await expect((await accountHandoff).ok()).toBeTruthy();

        await expect(page).toHaveURL(/\/profile$/);
        await expect(page).not.toHaveURL(/\/sign-in|\/auth\//);
        expect(authRefreshRequests).toEqual([]);
    } finally {
        await context.close();
    }
});
