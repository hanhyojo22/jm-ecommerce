import { expect, test } from '@playwright/test';
import { openAsRole } from './support/role-page.js';

test('seller catalog shows real products and opens working details at desktop and mobile sizes', async ({ browser }, testInfo) => {
    const { context, page } = await openAsRole(browser, 'seller');

    try {
        await page.setViewportSize({ width: 1682, height: 800 });
        await page.goto('/seller/products');
        await page.getByRole('button', { name: 'Reject Optional' }).click();
        await expect(page.locator('[data-cookie-banner]')).toHaveCount(0);
        await expect(page.getByRole('heading', { name: 'Product catalog' })).toBeVisible();
        await expect(page.getByText('More filters')).toHaveCount(0);
        const desktopAlignment = await page.evaluate(() => {
            const header = document.querySelector('.seller-products-header').getBoundingClientRect();
            const catalog = document.querySelector('.seller-products-catalog').getBoundingClientRect();
            const container = document.querySelector('.seller-workspace-page').getBoundingClientRect();
            const workspace = document.querySelector('.workspace-main').getBoundingClientRect();
            return {
                headerLeft: header.left,
                catalogLeft: catalog.left,
                leftSlack: Math.round(container.left - workspace.left),
                rightSlack: Math.round(workspace.right - container.right),
                catalogWidth: catalog.width,
            };
        });
        expect(Math.abs(desktopAlignment.headerLeft - desktopAlignment.catalogLeft)).toBeLessThanOrEqual(1);
        // Centred in the content column, not pinned to the sidebar.
        expect(desktopAlignment.leftSlack).toBe(desktopAlignment.rightSlack);
        expect(desktopAlignment.catalogWidth).toBe(1336);
        await expect(page.getByRole('table').getByText('QA Canvas Tote')).toBeVisible();
        await expect(page.getByRole('table').getByText('₱19.99', { exact: true })).toBeVisible();
        await expect(page.getByText('Other Seller QA Product')).toHaveCount(0);
        await page.screenshot({ path: testInfo.outputPath('seller-products-desktop.png'), fullPage: true });
        await page.getByRole('navigation', { name: 'Product status' }).getByRole('link', { name: 'Draft', exact: true }).click();
        await expect(page.getByRole('table')).toContainText('QA Approval Draft');
        await expect(page.getByRole('table')).not.toContainText('QA Canvas Tote');
        await page.goto('/seller/products');
        await page.locator('#product-search').fill('QA Canvas Tote');
        await page.getByRole('search').getByRole('button', { name: 'Search products' }).click();
        await expect(page.getByRole('table')).toContainText('QA Canvas Tote');
        await expect(page.getByRole('table')).not.toContainText('QA Chat Sample');
        await expect(page.locator('.seller-products-catalog table')).toHaveCSS('table-layout', 'auto');
        await expect(page.locator('.seller-products-search')).toHaveCSS('width', '230px');
        await page.goto('/seller/products');
        await page.getByRole('row').filter({ hasText: 'QA Canvas Tote' }).getByRole('link', { name: 'Details' }).click();
        await expect(page.getByRole('heading', { name: 'QA Canvas Tote' })).toBeVisible();
        const currentStock = (await page.getByText('Inventory', { exact: true }).locator('..').locator('dd').innerText()).split(' ')[0].replaceAll(',', '');
        await page.getByRole('link', { name: 'Edit listing' }).click();
        await expect(page.getByLabel('Stock quantity')).toHaveValue(currentStock);
        await page.goto('/seller/products');
        await page.setViewportSize({ width: 1024, height: 800 });
        await expect(page.getByRole('table')).toBeHidden();
        expect(await page.evaluate(() => document.querySelector('.seller-products-catalog').getBoundingClientRect().left - document.querySelector('.workspace-main').getBoundingClientRect().left)).toBe(25);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
        await page.setViewportSize({ width: 390, height: 844 });
        expect(await page.evaluate(() => document.querySelector('.seller-products-catalog').getBoundingClientRect().left - document.querySelector('.workspace-main').getBoundingClientRect().left)).toBe(17);
        await expect(page.getByRole('navigation', { name: 'Product status' }).getByRole('link', { name: 'Suspended' })).toBeInViewport();
        await page.screenshot({ path: testInfo.outputPath('seller-products-mobile.png'), fullPage: true });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    } finally {
        await context.close();
    }
});

test('seller workspace sections centre with their catalog gutters', async ({ browser }) => {
    test.setTimeout(90_000);
    const { context, page } = await openAsRole(browser, 'seller');
    const sections = ['/seller/orders', '/messages?context=selling', '/seller/reviews', '/seller/earnings', '/seller/store-settings'];

    try {
        for (const [width, height, expectedPadding] of [[1682, 800, 32], [390, 844, 17]]) {
            await page.setViewportSize({ width, height });

            for (const section of sections) {
                await page.goto(section);
                const containerSelector = section.startsWith('/messages') ? '.account-measure-messages' : '.seller-workspace-page';
                await expect(page.locator(containerSelector)).toBeVisible();

                const layout = await page.evaluate((selector) => {
                    const element = document.querySelector(selector);
                    const style = getComputedStyle(element);
                    const container = element.getBoundingClientRect();
                    const workspace = document.querySelector('.workspace-main').getBoundingClientRect();
                    const header = document.querySelector('.workspace-main > header:not([data-header-design])');

                    return {
                        leftSlack: Math.round(container.left - workspace.left),
                        rightSlack: Math.round(workspace.right - container.right),
                        paddingInline: parseFloat(style.paddingLeft),
                        width: container.width - parseFloat(style.paddingLeft) * 2,
                        headerLeft: header ? header.getBoundingClientRect().left + parseFloat(getComputedStyle(header).paddingLeft) : null,
                        contentLeft: container.left + parseFloat(style.paddingLeft),
                    };
                }, containerSelector);

                // Centred in the content column at the workspace measure, with the prototype gutters.
                expect(layout.leftSlack, section).toBe(layout.rightSlack);
                expect(layout.paddingInline, section).toBe(expectedPadding);
                if (width === 1682) {
                    // Messages and Orders stretch across the whole content column; the rest take the 1400px measure.
                    expect(layout.width, section).toBe({ '/messages?context=selling': 1396, '/seller/orders': 1386 }[section] ?? 1336);
                }
                if (layout.headerLeft !== null) {
                    expect(layout.headerLeft, section).toBe(layout.contentLeft);
                }
                expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth), section).toBe(true);
            }

            await page.goto('/seller/orders');
            await page.getByRole('link', { name: 'QA-REVIEW-001' }).click();
            const orderModal = page.getByRole('dialog', { name: 'QA-REVIEW-001' });
            await expect(orderModal).toBeVisible();
            await expect(page).toHaveURL(/\/seller\/orders\?order=/);
            await orderModal.getByRole('link', { name: 'Full page' }).click();
            await expect(page.getByRole('heading', { name: 'Order details' })).toBeVisible();
            expect(await page.evaluate(() => {
                const container = document.querySelector('.seller-workspace-page').getBoundingClientRect();
                const workspace = document.querySelector('.workspace-main').getBoundingClientRect();
                return Math.round((container.left - workspace.left) - (workspace.right - container.right));
            })).toBe(0);
        }
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

        await page.getByRole('heading', { name: 'Overview', exact: true }).click();
        await page.keyboard.press('s');
        await expect(page.getByRole('textbox', { name: 'Search your products by name or SKU' })).toBeFocused();

        await page.getByRole('heading', { name: 'Overview', exact: true }).click();
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

        await expect(page.getByRole('heading', { name: 'Overview', exact: true })).toBeVisible();
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
            recentOrders.getByText('No orders yet').or(recentOrders.locator('article').first())
        ).toBeVisible();
    } finally {
        await context.close();
    }
});

test('seller can persist overview visibility preferences and restore defaults', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'seller');

    try {
        await page.goto('/seller');
        await page.getByRole('button', { name: 'Accept All' }).click();
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
        await expect(page.getByRole('row').filter({ hasText: 'QA Canvas Tote' }).getByText('Published', { exact: true })).toBeVisible();
    } finally {
        await context.close();
    }
});

test('seller product selection fits and remains usable on mobile', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'seller');

    try {
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/seller/products');
        await page.getByRole('button', { name: 'Reject Optional' }).click();
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

test('seller remains signed in when Account and the workspace switch are opened after the session token expires', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'seller');
    const authRefreshRequests = [];

    try {
        page.on('request', (request) => {
            if (new URL(request.url()).pathname === '/auth/refresh') {
                authRefreshRequests.push(request.url());
            }
        });

        await page.goto('/seller');
        await context.clearCookies({ name: '__session' });
        await context.setExtraHTTPHeaders({});
        const accountLink = page.getByRole('complementary', { name: 'Seller Center sidebar' })
            .getByRole('link', { name: 'Account', exact: true });
        const accountHandoff = page.waitForResponse((response) => response.url().endsWith('/auth/session') && response.request().method() === 'POST');
        await accountLink.click();
        await expect((await accountHandoff).ok()).toBeTruthy();

        await expect(page).toHaveURL(/\/profile$/);
        await expect(page).not.toHaveURL(/\/sign-in|\/auth\//);

        await page.getByRole('button', { name: 'Account menu' }).click();
        const switchButton = page.locator('#header-account-menu').getByRole('button', { name: 'Switch Account' });
        await expect(switchButton).toBeVisible();
        await context.clearCookies({ name: '__session' });
        const switchHandoff = page.waitForResponse((response) => response.url().endsWith('/auth/session') && response.request().method() === 'POST');
        await switchButton.click();
        await expect((await switchHandoff).ok()).toBeTruthy();
        await expect(page).toHaveURL(/https?:\/\/[^/]+\/$/);
        await expect(page).not.toHaveURL(/\/sign-in|\/auth\//);
        expect(authRefreshRequests).toEqual([]);
    } finally {
        await context.close();
    }
});

test('seller order modal renews the session, preloads neighbours and recovers from a failed load', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'seller');
    const modalRequests = [];
    page.on('request', (request) => {
        if (request.headers()['x-order-modal'] === '1') {
            modalRequests.push(request);
        }
    });

    try {
        await page.setViewportSize({ width: 1682, height: 800 });
        await page.goto('/seller/orders');
        const orderLinks = page.locator('table tbody th a');
        const [firstNumber, secondNumber] = await orderLinks.evaluateAll((links) => links.slice(0, 2).map((link) => link.textContent.trim()));

        await orderLinks.first().hover();
        await expect.poll(() => modalRequests.length, 'resting on an order preloads it').toBeGreaterThan(0);
        await orderLinks.first().click();
        await expect(page.getByRole('dialog', { name: firstNumber })).toBeVisible();
        await expect.poll(() => new Set(modalRequests.map((request) => request.url())).size, 'the next order is preloaded').toBeGreaterThan(1);
        expect(modalRequests.every((request) => request.headers()['x-requires-session'] === '1')).toBe(true);

        await page.keyboard.press('ArrowRight');
        await expect(page.getByRole('dialog', { name: secondNumber })).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.getByRole('dialog')).toBeHidden();

        await page.reload();
        await page.route(/\/seller\/orders\/[^/?]+$/, (route) => (route.request().headers()['x-order-modal'] === '1' ? route.fulfill({ status: 500, body: '' }) : route.continue()));
        await orderLinks.first().click();
        await expect(page.getByText('We couldn’t load this order.')).toBeVisible();
        await page.unrouteAll();
        await page.getByRole('button', { name: 'Try again' }).click();
        await expect(page.getByRole('dialog', { name: firstNumber })).toBeVisible();
        await expect(page.getByText('We couldn’t load this order.')).toBeHidden();
    } finally {
        await context.close();
    }
});

test('seller order actions confirm in an in-page dialog instead of the browser prompt', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'seller');
    const nativeDialogs = [];
    page.on('dialog', (dialog) => {
        nativeDialogs.push(dialog.message());
        dialog.dismiss();
    });

    try {
        await page.setViewportSize({ width: 1280, height: 900 });
        await page.goto('/seller/orders');
        await page.locator('table tbody th a').first().click();
        const orderModal = page.locator('dialog.sc-modal');
        await expect(orderModal.locator('.sc-modal-foot')).toBeVisible();
        // The seeded orders are all settled, so add the action form the modal shows for an unpaid delivery.
        await orderModal.locator('.sc-modal-foot').evaluate((foot) => foot.insertAdjacentHTML('beforeend', '<form method="POST" action="/seller/orders/qa/cod-collection" data-confirm-title="Record cash collected?" data-confirm="Record only cash you actually have." data-confirm-reference="Order QA-1" data-confirm-amount="₱19.99" data-confirm-from="Unpaid" data-confirm-to="Paid" data-confirm-action="Record cash"><button type="submit">Record cash collected</button></form>'));
        const recordCash = orderModal.getByRole('button', { name: 'Record cash collected' });

        const submits = [];
        await page.route(/\/seller\/orders\/[^/]+\/cod-collection$/, (route) => {
            submits.push(route.request().method());
            return route.fulfill({ status: 302, headers: { location: '/seller/orders' } });
        });

        await recordCash.click();
        const confirmation = page.getByRole('dialog', { name: 'Record cash collected?' });
        await expect(confirmation).toBeVisible();
        await expect(confirmation.getByRole('button', { name: 'Record cash' })).toBeFocused();
        await expect(confirmation.getByText('Order QA-1', { exact: true })).toBeVisible();
        await expect(confirmation.getByText('₱19.99', { exact: true })).toBeVisible();
        await expect(confirmation.getByText('Paid', { exact: true })).toBeVisible();
        await confirmation.getByRole('button', { name: 'Cancel' }).click();
        await expect(confirmation).toBeHidden();
        await expect(orderModal).toBeVisible();
        expect(submits).toEqual([]);

        await recordCash.click();
        await expect(confirmation).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(confirmation).toBeHidden();
        await expect(orderModal).toBeVisible();
        expect(submits).toEqual([]);

        await recordCash.click();
        await confirmation.getByRole('button', { name: 'Record cash' }).click();
        await expect.poll(() => submits.length).toBe(1);
        expect(nativeDialogs).toEqual([]);
    } finally {
        await context.close();
    }
});
