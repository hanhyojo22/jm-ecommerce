import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { openAsRole } from './support/role-page.js';

test('buyer, seller, and support complete a return case', async ({ browser }) => {
    test.setTimeout(90_000);
    const buyer = await openAsRole(browser, 'customer');
    try {
        await buyer.page.goto('/orders');
        await rejectOptionalCookies(buyer.page);
        await buyer.page.locator('article').filter({ hasText: 'QA-REVIEW-001' }).getByRole('link', { name: 'View order' }).click();
        const returns = buyer.page.locator('#returns');
        await returns.getByRole('spinbutton').fill('1');
        await returns.getByLabel('Details').fill('The item arrived damaged and I need help.');
        await returns.getByRole('button', { name: 'Request seller review' }).click();
        await expect(buyer.page.getByRole('heading', { name: 'Order QA-REVIEW-001' })).toBeVisible();

        const seller = await openAsRole(browser, 'seller');
        try {
            // Rejecting a case asks the seller to confirm, and Playwright dismisses dialogs by default.
            seller.page.on('dialog', (dialog) => dialog.accept());
            await seller.page.goto('/seller/returns');
            await rejectOptionalCookies(seller.page);
            await seller.page.getByRole('link', { name: /QA-REVIEW-001/ }).click();
            await seller.page.getByLabel('Response with reasons').fill('I cannot approve this without support review.');
            await seller.page.getByRole('button', { name: 'Reject with reason' }).click();
            await expect(seller.page.getByText('Rejection awaiting buyer')).toBeVisible();
        } finally {
            await seller.context.close();
        }

        await buyer.page.reload();
        await buyer.page.getByLabel('Why should support review this?').fill('The damage is visible and the item cannot be used.');
        await buyer.page.getByRole('button', { name: 'Appeal to JMMart' }).click();

        const admin = await openAsRole(browser, 'admin');
        try {
            await admin.page.goto('/admin/returns');
            await rejectOptionalCookies(admin.page);
            await admin.page.getByRole('link', { name: /QA-REVIEW-001/ }).click();
            await admin.page.getByLabel('Reasoned decision').fill('The product arrived damaged. A full refund is appropriate.');
            await admin.page.getByLabel('Waive the physical return').check();
            await admin.page.getByRole('button', { name: 'Record final decision' }).click();
            await expect(admin.page.getByText('Remedy in progress')).toBeVisible();
        } finally {
            await admin.context.close();
        }

        const fulfillingSeller = await openAsRole(browser, 'seller');
        try {
            await fulfillingSeller.page.goto('/seller/returns');
            await rejectOptionalCookies(fulfillingSeller.page);
            await fulfillingSeller.page.getByRole('link', { name: /QA-REVIEW-001/ }).click();
            await fulfillingSeller.page.getByLabel('Payment date').fill(new Date().toISOString().slice(0, 10));
            await fulfillingSeller.page.getByLabel('Payment proof').setInputFiles({
                name: 'refund.png',
                mimeType: 'image/png',
                buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lXcAAAAASUVORK5CYII=', 'base64'),
            });
            await fulfillingSeller.page.getByRole('button', { name: 'Record seller-paid refund' }).click();
            await expect(fulfillingSeller.page.getByText('Awaiting buyer confirmation')).toBeVisible();
        } finally {
            await fulfillingSeller.context.close();
        }

        await buyer.page.reload();
        await buyer.page.getByRole('button', { name: 'Confirm receipt' }).click();
        await expect(buyer.page.getByText('Completed')).toBeVisible();
    } finally {
        await buyer.context.close();
    }
});

async function rejectOptionalCookies(page) {
    await Promise.all([
        page.waitForEvent('domcontentloaded'),
        page.locator('[data-cookie-banner] [data-cookie-action="reject"]').click(),
    ]);
    await expect(page.locator('[data-cookie-banner]')).toHaveCount(0);
}

test('guest can browse a product and is prompted to sign in before adding it to cart', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByRole('navigation', { name: 'Marketplace sections' })).toBeVisible();
    await page.getByRole('link', { name: 'Sign In', exact: true }).click();
    await expect(page).toHaveURL(/\/sign-in$/);
    await expect(page.getByRole('tab', { name: 'Create account' })).toBeVisible();

    await page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
    await expect(page.getByRole('heading', { name: 'QA Canvas Tote', exact: true })).toBeVisible();
    await expect(page.locator('#discussions')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Sign in to add to cart' })).toHaveAttribute('href', /sign-in/);
});

test('customer can add a newly loaded arrival to the cart', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');

    try {
        await page.goto('/');
        await rejectOptionalCookies(page);
        const arrivals = page.locator('#new-arrivals');
        await arrivals.getByRole('link', { name: 'Load More' }).click();
        const arrival = arrivals.locator('article').filter({ hasText: 'QA Arrival 21' });
        await arrival.getByRole('button', { name: 'Add to cart' }).click();
        await expect(arrival.getByRole('status')).toHaveText('Added to cart.');

        await page.goto('/cart');
        await page.getByRole('button', { name: 'Remove QA Arrival 21 from your cart' }).click();
        await expect(page.getByRole('button', { name: 'Remove QA Arrival 21 from your cart' })).toHaveCount(0);
    } finally {
        await context.close();
    }
});

test('cart badge responds while the add carries its own session token, rolls back errors, and reconciles later adds', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');
    let releaseRequest;
    const heldRequest = new Promise((resolve) => { releaseRequest = resolve; });
    let sessionRefreshes = 0;
    const addAuthorizations = [];

    try {
        await page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        const cartLink = page.locator('[data-cart-link]');
        const startingCount = Number((await cartLink.getAttribute('aria-label')).match(/\d+/)[0]);
        const form = page.locator('#purchase form[data-add-to-cart]');
        await page.route('**/auth/session', async (route) => {
            sessionRefreshes++;
            await route.continue();
        });
        await page.route('**/cart/products/**', async (route) => {
            addAuthorizations.push(route.request().headers().authorization ?? '');
            await heldRequest;
            await route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ errors: { quantity: ['This item is no longer available.'] } }) });
        });

        await form.getByRole('button', { name: 'Add to cart' }).click();
        await expect(cartLink).toHaveAttribute('aria-label', new RegExp(`^Cart: ${startingCount + 1} items?$`));
        await expect(form).toHaveAttribute('aria-busy', 'true');
        await expect(form.locator('[data-cart-feedback]')).toHaveText('Adding to cart…');

        releaseRequest();
        await expect(cartLink).toHaveAttribute('aria-label', new RegExp(`^Cart: ${startingCount} items?$`));
        await expect(form.locator('[data-cart-feedback]')).toHaveText('This item is no longer available.');
        await expect(form).not.toHaveAttribute('aria-busy');

        // The add authenticates itself, so it never waits on the session bridge for a cookie.
        expect(addAuthorizations).toEqual([expect.stringMatching(/^Bearer \S+$/)]);
        expect(sessionRefreshes).toBe(0);

        await page.unroute('**/cart/products/**');
        await form.getByRole('button', { name: 'Add to cart' }).click();
        await expect(form.locator('[data-cart-feedback]')).toHaveText('Added to cart.');
        await expect(form).not.toHaveAttribute('aria-busy');
        await expect(cartLink).toHaveAttribute('aria-label', new RegExp(`^Cart: ${startingCount + 1} items?$`));
        await form.getByRole('button', { name: 'Add to cart' }).click();
        await expect(form.locator('[data-cart-feedback]')).toHaveText('Added to cart.');
        await expect(form).not.toHaveAttribute('aria-busy');
        await expect(cartLink).toHaveAttribute('aria-label', new RegExp(`^Cart: ${startingCount + 2} items?$`));

        // A token the server rejects is dropped, so the retry presents the cookie the bridge minted
        // rather than the same refused credential.
        const retryAuthorizations = [];
        let rejectOnce = true;
        await page.route('**/cart/products/**', async (route) => {
            retryAuthorizations.push(route.request().headers().authorization ?? '');

            if (rejectOnce) {
                rejectOnce = false;
                await route.fulfill({ status: 401, contentType: 'application/json', body: '{}' });

                return;
            }

            await route.continue();
        });
        await form.getByRole('button', { name: 'Add to cart' }).click();
        await expect(form.locator('[data-cart-feedback]')).toHaveText('Added to cart.');
        await expect(cartLink).toHaveAttribute('aria-label', new RegExp(`^Cart: ${startingCount + 3} items?$`));
        expect(retryAuthorizations).toEqual([expect.stringMatching(/^Bearer \S+$/), '']);
        expect(sessionRefreshes).toBe(1);
    } finally {
        releaseRequest();
        await context.close();
    }
});

test('buy now puts the session in a cookie before it navigates to checkout', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');
    const events = [];

    try {
        await page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await page.route('**/auth/session', async (route) => {
            events.push('session');
            await route.continue();
        });
        await page.route('**/cart/products/**', async (route) => {
            events.push('add');
            await route.continue();
        });

        await page.locator('#purchase').getByRole('button', { name: 'Buy now' }).click();
        await expect(page).toHaveURL(/\/checkout$/);

        // Checkout is reached by a plain navigation, which carries no Authorization header.
        expect(events).toEqual(['add', 'session']);
    } finally {
        await context.close();
    }
});

test('seller shops another store and returns to Seller Center with the same account', async ({ browser }) => {
    test.setTimeout(60_000);
    const { context, page } = await openAsRole(browser, 'seller');

    try {
        await page.goto('/seller');
        await page.getByRole('button', { name: 'Account menu' }).click();
        if (await page.locator('#header-account-menu [data-workspace-switch] input[name="workspace"]').inputValue() === 'seller') {
            await page.locator('#header-account-menu').getByRole('button', { name: 'Switch Account' }).click();
            await expect(page).toHaveURL(/\/seller$/);
            await page.getByRole('button', { name: 'Account menu' }).click();
        }
        await expect(page.locator('#header-account-menu [data-workspace-switch] input[name="workspace"]')).toHaveValue('customer');
        await page.locator('#header-account-menu').getByRole('button', { name: 'Switch Account' }).click();
        await expect(page).toHaveURL(/https?:\/\/[^/]+\/$/);

        await page.goto('/delivery-addresses');
        await rejectOptionalCookies(page);
        await page.getByLabel('Recipient name').fill('QA Seller');
        await page.getByLabel('Mobile number').fill('+639171234567');
        await page.getByLabel('Street address').fill('20 QA Street');
        await page.getByLabel('City').fill('Manila');
        await page.getByLabel('Province').fill('Metro Manila');
        await page.getByLabel('Postal code').fill('1000');
        await page.getByRole('button', { name: 'Save address' }).click();
        await expect(page.getByText('20 QA Street, Manila, Metro Manila 1000', { exact: true })).toBeVisible();

        await page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await expect(page.locator('#purchase').getByRole('button', { name: 'Add to cart' })).toHaveCount(0);

        await page.goto('/stores/qa-southwind-store/products/other-qa-product');
        await page.locator('#purchase').getByRole('button', { name: 'Add to cart' }).click();
        await page.getByRole('link', { name: 'Cart: 1 item' }).click();
        await page.getByRole('link', { name: /^Check out(?:\s*\(\s*\d+\s*\))?$/ }).click();
        await page.getByLabel('Use a saved address').selectOption({ label: 'QA Seller · 20 QA Street, Manila' });
        await expect(page.getByLabel('Street address')).toHaveValue('20 QA Street');
        await expect(page.getByLabel('Province')).toHaveValue('Metro Manila');
        await expect(page.getByLabel('Postal code')).toHaveValue('1000');
        await page.locator('#checkout-form').getByRole('button', { name: /Place order/ }).click();
        await expect(page).toHaveURL(/\/orders\/[0-9A-HJKMNP-TV-Z]+$/i);
        await expect(page.getByText('Other Seller QA Product').first()).toBeVisible();

        await page.getByRole('button', { name: 'Account menu' }).click();
        await expect(page.getByText('Wishlist')).toHaveCount(0);
        await page.locator('#header-account-menu').getByRole('link', { name: 'My Purchases' }).click();
        await expect(page).toHaveURL(/\/orders$/);
        await expect(page.getByText('Other Seller QA Product').first()).toBeVisible();
        await page.getByRole('button', { name: 'Account menu' }).click();
        await expect(page.locator('#header-account-menu [data-workspace-switch] input[name="workspace"]')).toHaveValue('seller');
        await page.locator('#header-account-menu').getByRole('button', { name: 'Switch Account' }).click();
        await expect(page).toHaveURL(/\/seller$/);
    } finally {
        await context.close();
    }
});

test('customer can place a COD order from the product page through checkout', async ({ browser }, testInfo) => {
    test.setTimeout(60_000);
    const { context, page } = await openAsRole(browser, 'customer');

    try {
        await page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        const cartLink = page.locator('[data-cart-link]');
        const startingCartCount = Number((await cartLink.getAttribute('aria-label')).match(/\d+/)[0]);
        await page.locator('#purchase').getByRole('button', { name: 'Add to cart' }).click();
        await expect(cartLink).toHaveAttribute('aria-label', new RegExp(`^Cart: ${startingCartCount + 1} items?$`));
        await page.locator('#purchase').getByRole('button', { name: 'Add to cart' }).click();
        await expect(cartLink).toHaveAttribute('aria-label', new RegExp(`^Cart: ${startingCartCount + 2} items?$`));
        await cartLink.click();
        await expect(page).toHaveURL(/\/cart$/);
        await page.getByRole('checkbox', { name: 'Select QA Canvas Tote' }).check();
        const checkoutLink = page.getByRole('link', { name: /^Check out(?:\s*\(\s*\d+\s*\))?$/ });
        await expect(checkoutLink).toHaveAttribute('aria-disabled', 'false');
        await checkoutLink.click();
        await page.getByLabel('Recipient name').fill('Ava QA Customer');
        await page.getByLabel('Mobile number').fill('+639171234567');
        await page.getByLabel('Street address').fill('10 QA Street');
        await page.getByLabel('City').fill('Manila');
        await page.getByLabel('Province').fill('Metro Manila');
        await page.getByLabel('Postal code').fill('1000');
        await page.getByLabel('Delivery note').fill('Blue gate');
        await expect(page.getByRole('heading', { name: 'Delivery by store' })).toBeVisible();
        const deliveryBox = await page.locator('#delivery-heading').boundingBox();
        const summaryBox = await page.locator('#checkout-summary-heading').boundingBox();
        expect(summaryBox.x).toBeGreaterThan(deliveryBox.x);
        expect(await page.locator('#checkout-summary-heading').locator('xpath=ancestor::aside').evaluate(element => getComputedStyle(element).position)).toBe('sticky');
        await page.screenshot({ path: testInfo.outputPath('checkout-desktop.png'), fullPage: true });
        await page.locator('#checkout-form').getByRole('button', { name: /Place order/ }).click();

        await expect(page).toHaveURL(/\/orders\/[0-9A-HJKMNP-TV-Z]+$/i);
        await expect(page.getByRole('heading', { name: 'Order details' })).toBeVisible();
        await expect(page.getByText('QA Canvas Tote')).toBeVisible();
        await expect(page.getByText('Blue gate')).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Order summary' }).locator('..').getByText('COD', { exact: true })).toBeVisible();
    } finally {
        await context.close();
    }
});

test('mobile checkout stacks the summary and shows server field errors', async ({ browser }, testInfo) => {
    const { context, page } = await openAsRole(browser, 'customer', { viewport: { width: 390, height: 844 } });

    try {
        await page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await rejectOptionalCookies(page);
        await page.locator('#purchase').getByRole('button', { name: 'Add to cart' }).click();
        const cartLink = page.getByRole('link', { name: /Cart: \d+ items?/ });
        await expect(cartLink).toBeVisible();
        await cartLink.click();
        await page.getByRole('link', { name: /^Check out(?:\s*\(\s*\d+\s*\))?$/ }).click();
        await expect(page).toHaveURL(/\/checkout$/);

        await expect(page.getByRole('button', { name: /Place order/ })).toHaveCount(1);
        const form = page.locator('#checkout-form');
        const summary = page.locator('#checkout-summary-heading');
        const payment = page.locator('#payment-heading');
        expect((await summary.boundingBox()).y).toBeGreaterThan((await payment.boundingBox()).y);
        await page.screenshot({ path: testInfo.outputPath('checkout-mobile.png'), fullPage: true });
        await page.getByLabel('Recipient name').fill('');
        await page.getByLabel('Mobile number').fill('');
        await page.getByLabel('Street address').fill('');
        await page.getByLabel('City').fill('');
        await form.evaluate(element => { element.noValidate = true; });
        await page.getByRole('button', { name: /Place order/ }).click();
        await expect(page.getByLabel('Recipient name')).toHaveAttribute('aria-invalid', 'true');
        await expect(page.locator('#recipient_name-error')).toBeVisible();
        await expect(page.getByLabel('Mobile number')).toHaveAttribute('aria-describedby', 'phone-error');

        await page.getByLabel('Recipient name').fill('Ava QA Customer');
        await page.getByLabel('Mobile number').fill('+639171234567');
        await page.getByLabel('Street address').fill('10 QA Street');
        await page.getByLabel('City').fill('Manila');
        await page.getByLabel('Province').fill('Metro Manila');
        await page.getByLabel('Postal code').fill('1000');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();
    } finally {
        await context.close();
    }
});

test('customer adds an item to the cart on the session token the request carries itself', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');
    let addAuthorization;

    try {
        await page.route('**/cart/products/**', async (route) => {
            const headers = { ...route.request().headers() };
            addAuthorization = headers.authorization;
            delete headers['x-playwright-auth-key'];
            delete headers['x-playwright-identity'];
            await route.continue({ headers });
        });
        await page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        const startingCartCount = Number((await page.locator('[data-cart-link]').getAttribute('aria-label')).match(/\d+/)[0]);
        const addToCartForm = page.locator('#purchase form');
        await expect(addToCartForm).toHaveAttribute('data-clerk-session-refresh', /auth\/session/);
        await expect(addToCartForm).toHaveAttribute('action', /cart\/products\//);
        const add = page.waitForResponse((response) => /\/cart\/products\//.test(response.url()) && response.request().method() === 'POST');
        await page.locator('#purchase').getByRole('button', { name: 'Add to cart' }).click();
        await expect((await add).ok()).toBeTruthy();
        expect(addAuthorization).toMatch(/^Bearer \S+$/);

        await expect(page).toHaveURL(/\/stores\/qa-northwind-store\/products\/qa-canvas-tote$/);
        await expect(page.locator('[data-cart-link]')).toHaveAttribute('aria-label', new RegExp(`^Cart: ${startingCartCount + 1} items?$`));
    } finally {
        await context.close();
    }
});

test('seller refreshes an expired session before posting a review response', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'seller');

    try {
        await page.goto('/seller/reviews');
        await page.locator('[data-seller-reviews] [data-review-thread]').first().getByRole('button', { name: /^(Reply|Edit response)$/ }).click();
        await page.getByLabel('Seller response').fill('Thank you for your feedback.');
        const refresh = page.waitForResponse((response) => response.url().endsWith('/auth/session') && response.request().method() === 'POST');
        await page.getByRole('button', { name: /^(Post|Update) response$/ }).click();

        await expect((await refresh).ok()).toBeTruthy();
        await expect(page).toHaveURL(/\/seller\/reviews$/);
        await expect(page.locator('article').getByText('Thank you for your feedback.')).toBeVisible();
        await expect(page.locator('article').getByText('QA Northwind Store', { exact: true })).toBeVisible();
    } finally {
        await context.close();
    }
});

test('buyer and seller continue a review thread without reloading and keep it compact on mobile', async ({ browser }, testInfo) => {
    test.setTimeout(90_000);
    const seller = await openAsRole(browser, 'seller');
    const buyer = await openAsRole(browser, 'customer');
    const pageErrors = [];
    for (const page of [seller.page, buyer.page]) page.on('pageerror', (error) => pageErrors.push(error.message));

    try {
        await seller.page.goto('/seller/reviews');
        await rejectOptionalCookies(seller.page);
        const sellerThread = seller.page.locator('[data-seller-reviews] [data-review-thread]').filter({ hasText: 'QA Canvas Tote' }).first();
        const responseButton = sellerThread.getByRole('button', { name: /^(Reply|Edit response)$/ });
        const alreadyResponded = await responseButton.textContent() === 'Edit response';
        await responseButton.click();
        await sellerThread.getByLabel('Seller response').fill('Thanks for reviewing the tote.');
        await sellerThread.getByRole('button', { name: alreadyResponded ? 'Update response' : 'Post response' }).click();
        await expect(sellerThread).toContainText('Thanks for reviewing the tote.');

        await seller.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote#reviews');

        await buyer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote#reviews');
        await rejectOptionalCookies(buyer.page);
        const thread = buyer.page.locator('#reviews article');
        await expect(thread).toContainText('QA Northwind Store');
        await expect(thread).not.toContainText('QA Seller');
        const storeResponse = thread.locator('.review-reply-composer').first();
        await storeResponse.locator('summary').click();
        await expect(storeResponse.getByLabel('Write a reply')).toBeVisible();
        await storeResponse.getByLabel('Write a reply').fill('Does it come in blue?');
        let finishSubmission;
        const pendingSubmission = new Promise((resolve) => { finishSubmission = resolve; });
        await buyer.page.route(/\/livewire[^/]*\/update$/, async (route) => {
            await pendingSubmission;
            await route.continue();
        });
        const sendReply = storeResponse.getByRole('button', { name: 'Send' });
        await sendReply.click();
        await expect(sendReply).toBeDisabled();
        finishSubmission();
        await expect(thread).toContainText('Does it come in blue?');
        await buyer.page.unroute(/\/livewire[^/]*\/update$/);
        await expect(buyer.page).toHaveURL(/\/stores\/qa-northwind-store\/products\/qa-canvas-tote/);
        await thread.getByRole('button', { name: 'Hide replies' }).click();
        await expect(thread).not.toContainText('Does it come in blue?');
        await thread.getByRole('button', { name: /View replies/ }).click();
        await expect(thread).toContainText('Does it come in blue?');

        await seller.page.reload();
        await seller.page.route(/\/livewire[^/]*\/update$/, (route) => route.abort());
        await seller.page.locator('article').getByText('Reply', { exact: true }).click();
        await expect(seller.page.getByLabel('Write a reply')).toBeVisible();
        await seller.page.unroute(/\/livewire[^/]*\/update$/);
        await seller.page.getByLabel('Write a reply').fill('Yes, blue is available.');
        await seller.page.getByRole('button', { name: 'Post reply' }).click();
        await expect(seller.page.locator('article')).toContainText('Yes, blue is available.');
        const originalReply = seller.page.locator('.review-reply').filter({ hasText: 'Does it come in blue?' }).first().locator('details.review-reply-composer');
        if (await originalReply.getAttribute('open') !== null) await originalReply.locator('summary').click();
        await seller.page.route(/\/livewire[^/]*\/update$/, (route) => route.abort());
        await originalReply.locator('summary').click();
        await expect(originalReply.getByLabel('Write a reply')).toBeVisible();
        await seller.page.unroute(/\/livewire[^/]*\/update$/);

        await buyer.page.reload();
        await expect(thread.getByPlaceholder('Write a reply...')).toHaveCount(2);
        const sellerFollowup = thread.locator('.review-reply-composer').last();
        await sellerFollowup.locator('summary').click();
        await sellerFollowup.getByLabel('Write a reply').fill('Perfect, thank you.');
        await sellerFollowup.getByRole('button', { name: 'Send' }).click();
        await expect(thread).toContainText('Perfect, thank you.');

        for (const width of [1440, 820, 390]) {
            await buyer.page.setViewportSize({ width, height: 844 });
            await buyer.page.reload();
            await expect(thread).toContainText('Perfect, thank you.');
            const sizes = await buyer.page.evaluate(() => ({ viewport: document.documentElement.clientWidth, page: document.documentElement.scrollWidth }));
            expect(sizes.page, `${width}px review page`).toBeLessThanOrEqual(sizes.viewport);
            if (width === 1440 || width === 390) {
                await thread.scrollIntoViewIfNeeded();
                await buyer.page.screenshot({ path: testInfo.outputPath(`review-thread-${width}.png`), fullPage: true });
            }
        }
        expect(pageErrors).toEqual([]);
    } finally {
        await seller.context.close();
        await buyer.context.close();
    }
});

test('seller response stays on the form when session renewal fails', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'seller');

    try {
        await page.goto('/seller/reviews');
        await rejectOptionalCookies(page);
        await page.locator('[data-seller-reviews] [data-review-thread]').first().getByRole('button', { name: /^(Reply|Edit response)$/ }).click();

        // The composer arrives over Livewire, so it has to be open before the session is broken —
        // otherwise the 401 lands on the request that was still fetching it.
        const composer = page.getByLabel('Seller response');
        await expect(composer).toBeVisible();

        await page.route('**/auth/session', (route) => route.fulfill({ status: 401, body: 'Session expired' }));
        await composer.fill('Please keep this draft.');
        await page.getByRole('button', { name: /^(Post|Update) response$/ }).click();

        await expect(page.getByRole('alert')).toContainText('session could not be refreshed');
        await expect(page).toHaveURL(/\/seller\/reviews$/);
        await expect(composer).toHaveValue('Please keep this draft.');
    } finally {
        await context.close();
    }
});

test('seller reviews show real conversations and working replies at desktop and mobile sizes', async ({ browser }, testInfo) => {
    test.setTimeout(60_000);
    const seller = await openAsRole(browser, 'seller');
    const customer = await openAsRole(browser, 'customer');

    try {
        await seller.page.goto('/seller/store-settings');
        await rejectOptionalCookies(seller.page);
        await seller.page.getByLabel('Change logo').setInputFiles('public/images/categories/electronics.png');
        await seller.page.getByRole('button', { name: 'Save store settings' }).click();
        await expect(seller.page.getByText('Store details saved.')).toBeVisible();

        await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote#discussions');
        await rejectOptionalCookies(customer.page);
        await customer.page.locator('#discussion-body').fill('Does this tote come in blue?');
        await customer.page.getByRole('button', { name: 'Post message' }).click();
        await expect(customer.page.locator('#discussions')).toContainText('Does this tote come in blue?');

        await seller.page.goto('/seller/reviews');
        await expect(seller.page.getByRole('heading', { name: 'Reviews & conversations' })).toBeVisible();
        await expect(seller.page.locator('.seller-review-score')).toHaveText('5.0');
        await expect(seller.page.locator('[data-seller-reviews]')).toContainText('Does this tote come in blue?');
        await seller.page.locator('.seller-review-discussion').getByText('Reply', { exact: true }).first().click();
        expect(await seller.page.locator('.seller-review-discussion').getByRole('button', { name: 'Post reply' }).first().evaluate((button) => getComputedStyle(button).backgroundColor)).toBe('rgba(0, 0, 0, 0)');
        await seller.page.locator('.seller-review-discussion').getByLabel('Your reply').first().fill('Yes, blue is available.');
        const replyRequests = [];
        seller.page.on('requestfinished', (request) => {
            if (request.url().includes('/discussions') || request.url().includes('/seller/reviews')) {
                replyRequests.push(new URL(request.url()).pathname);
            }
        });
        await seller.page.locator('.seller-review-discussion').getByRole('button', { name: 'Post reply' }).first().click();
        await expect(seller.page).toHaveURL(/\/seller\/reviews$/);
        await expect(seller.page.locator('[data-seller-reviews]')).toContainText('Yes, blue is available.');
        const storeAvatar = seller.page.locator('.seller-review-discussion .seller-review-avatar-image').first();
        await expect(storeAvatar).toBeVisible();
        await expect.poll(() => storeAvatar.evaluate((image) => image.complete && image.naturalWidth > 0)).toBe(true);
        expect(replyRequests).toContainEqual(expect.stringMatching(/\/discussions$/));
        expect(replyRequests).not.toContain('/seller/reviews');
        await seller.page.locator('.seller-review-discussion').getByText('Reply', { exact: true }).last().click();
        await seller.page.locator('.seller-review-discussion').getByLabel('Your reply').last().fill('Any shade of blue works.');
        await seller.page.locator('.seller-review-discussion').getByRole('button', { name: 'Post reply' }).last().click();
        await expect(seller.page.locator('[data-seller-reviews]')).toContainText('Any shade of blue works.');
        expect(replyRequests).not.toContain('/seller/reviews');

        for (const width of [1440, 390]) {
            await seller.page.setViewportSize({ width, height: 900 });
            await seller.page.evaluate(() => window.scrollTo(0, 0));
            await seller.page.screenshot({ path: testInfo.outputPath(`seller-reviews-${width}.png`), fullPage: true });
            const sizes = await seller.page.evaluate(() => ({ viewport: document.documentElement.clientWidth, page: document.documentElement.scrollWidth }));
            expect(sizes.page, `${width}px seller reviews`).toBeLessThanOrEqual(sizes.viewport);
        }

        await seller.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote#discussions');
        await expect(seller.page.locator('#discussions .pd-discussion .pd-avatar.seller img').first()).toBeVisible();
        await expect(seller.page.locator('#discussions .pd-comment-form > .pd-avatar.seller img')).toBeVisible();
        await seller.page.locator('#discussions .pd-discussion-reply summary').first().click();
        expect(await seller.page.locator('#discussions .pd-reply-submit').first().evaluate((button) => getComputedStyle(button).backgroundColor)).toBe('rgba(0, 0, 0, 0)');
    } finally {
        await seller.context.close();
        await customer.context.close();
    }
});

test('admin refreshes an expired session before moderating a review', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'admin');

    try {
        await page.route('**/admin/reviews/*', async (route) => {
            const headers = { ...route.request().headers() };
            delete headers['x-playwright-auth-key'];
            delete headers['x-playwright-identity'];
            await route.continue({ headers });
        });
        await page.goto('/admin/reviews');
        const form = page.locator('form[action*="/admin/reviews/"]').first();
        await form.locator('input[name="reason"]').fill('Reviewed by QA');
        const refresh = page.waitForResponse((response) => response.url().endsWith('/auth/session') && response.request().method() === 'POST');
        await form.getByRole('button', { name: 'Save' }).click();

        await expect((await refresh).ok()).toBeTruthy();
        await expect(page).toHaveURL(/\/admin\/reviews$/);
        await expect(page.locator('form[action*="/admin/reviews/"]').first().locator('input[name="reason"]')).toHaveValue('Reviewed by QA');
    } finally {
        await context.close();
    }
});

test('buyer refreshes an expired session before messaging a seller', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');

    try {
        await page.route('**/messages/products/*', async (route) => {
            const headers = { ...route.request().headers() };
            delete headers['x-playwright-auth-key'];
            delete headers['x-playwright-identity'];
            await route.continue({ headers });
        });
        await page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        const refresh = page.waitForResponse((response) => response.url().endsWith('/auth/session') && response.request().method() === 'POST');
        await page.getByRole('button', { name: 'Message seller' }).click();

        await expect((await refresh).ok()).toBeTruthy();
        await expect(page).toHaveURL(/\/messages\/[^/]+$/);
        await expect(page.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
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

test('stalled session renewal does not trap navigation on the current page', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');

    try {
        await page.goto('/');
        await page.clock.install();
        await page.route('**/auth/session', () => new Promise(() => {}));

        await page.getByRole('link', { name: /Messages/ }).first().evaluate((link) => link.click());
        await expect(page.getByRole('link', { name: /Messages/ }).first()).toHaveAttribute('aria-busy', 'true');
        await page.clock.fastForward(10_000);

        await expect(page).toHaveURL(/\/messages$/);
    } finally {
        await context.close();
    }
});

test('messaging workspace adapts from split desktop panels to a mobile chat view', async ({ browser }, testInfo) => {
    const customer = await openAsRole(browser, 'customer');
    try {
        await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await customer.page.getByRole('button', { name: 'Message seller' }).click();
        await customer.page.setViewportSize({ width: 390, height: 844 });
        await customer.page.screenshot({ path: testInfo.outputPath('chat-buyer-mobile.png') });
        await expect(customer.page.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
        const composer = await customer.page.getByRole('button', { name: 'Send message' }).boundingBox();
        expect(composer.y + composer.height).toBeLessThanOrEqual(844);
    } finally {
        await customer.context.close();
    }

    const seller = await openAsRole(browser, 'seller');
    try {
        for (const [device, width] of [['desktop', 1440], ['tablet', 820], ['mobile', 390]]) {
            await seller.page.setViewportSize({ width, height: 844 });
            await seller.page.goto('/messages');
            await expect(seller.page.locator('[data-conversation-list]')).toBeVisible();
            if (width >= 1024) {
                await expect(seller.page.getByRole('heading', { name: 'Select a conversation' })).toBeVisible();
            } else {
                await expect(seller.page.getByRole('heading', { name: 'Select a conversation' })).toBeHidden();
            }
            const conversationUrl = await seller.page.locator('[data-conversation-list] a').filter({ hasText: 'QA Canvas Tote' }).getAttribute('href');
            await seller.page.goto(conversationUrl, { waitUntil: 'domcontentloaded' });
            await expect(seller.page.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
            if (width >= 1024) {
                await expect(seller.page.locator('[data-conversation-list]')).toBeVisible();
                await expect(seller.page.locator('[data-conversation-list] a[aria-current="page"]')).toBeVisible();
            } else {
                await expect(seller.page.locator('[data-conversation-list]')).toBeHidden();
                await expect(seller.page.getByRole('link', { name: 'Back to messages' })).toBeVisible();
            }
            const sizes = await seller.page.evaluate(() => ({ viewport: document.documentElement.clientWidth, page: document.documentElement.scrollWidth }));
            expect(sizes.page).toBeLessThanOrEqual(sizes.viewport);
            await seller.page.screenshot({ path: testInfo.outputPath(`chat-${device}.png`) });
        }
    } finally {
        await seller.context.close();
    }
});

test('conversation search and unread filter update the seller inbox', async ({ browser }) => {
    test.setTimeout(90_000);
    const customer = await openAsRole(browser, 'customer');
    try {
        await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await customer.page.getByRole('button', { name: 'Message seller' }).click();
        await customer.page.waitForURL(/\/messages\/[0-9A-HJKMNP-TV-Z]+$/i, { timeout: 30_000 });
        await customer.page.getByRole('textbox', { name: 'Write a message' }).fill('Unread filter check');
        await expect(customer.page.locator('#message-help')).toContainText('19/2000');
        await customer.page.getByRole('button', { name: 'Send message' }).click();
        await expect(customer.page.locator('#conversation-messages article[data-client-message-id]').getByText('Unread filter check', { exact: true })).toBeVisible();
    } finally {
        await customer.context.close();
    }

    const seller = await openAsRole(browser, 'seller');
    try {
        await seller.page.goto('/messages');
        await seller.page.getByRole('button', { name: 'Unread' }).click();
        await expect(seller.page.getByRole('button', { name: 'Unread' })).toHaveAttribute('aria-pressed', 'true');
        await expect(seller.page.locator('[data-conversation-list] a').filter({ hasText: 'QA Canvas Tote' })).toBeVisible();
        await seller.page.getByPlaceholder('Search conversations').fill('no matching customer');
        await expect(seller.page.getByRole('heading', { name: 'No matching conversations' })).toBeVisible();
        await seller.page.getByPlaceholder('Search conversations').fill('QA Customer');
        await expect(seller.page.locator('[data-conversation-list] a').filter({ hasText: 'QA Canvas Tote' })).toBeVisible();
    } finally {
        await seller.context.close();
    }
});

test('incoming message event refreshes the inbox preview and unread badge without navigation', async ({ browser }) => {
    // Two accounts, two live round trips: this one runs within a few seconds of the default
    // budget and tips over it whenever the machine is busy.
    test.setTimeout(90_000);

    const customer = await openAsRole(browser, 'customer');
    const seller = await openAsRole(browser, 'seller');

    try {
        await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await customer.page.getByRole('button', { name: 'Message seller' }).click();
        await seller.page.goto('/messages');
        await expect(seller.page.locator('[data-conversation-list] a').filter({ hasText: 'QA Canvas Tote' })).toBeVisible();
        await expect.poll(() => seller.page.evaluate(() => Object.entries(window.Echo?.connector.pusher.channels.channels ?? {}).some(([name, channel]) => name.startsWith('private-chat.inbox.') && channel.subscribed))).toBe(true);
        const unreadBefore = await seller.page.locator('[data-conversation-list] a').filter({ hasText: 'QA Canvas Tote' }).evaluate((link) => Number(link.querySelector('[aria-label$="unread messages"]')?.getAttribute('aria-label').split(' ')[0] ?? 0));
        await seller.page.evaluate(() => { window.__chatPageMarker = true; });

        await customer.page.getByRole('textbox', { name: 'Write a message' }).fill('Live inbox preview');
        await customer.page.getByRole('button', { name: 'Send message' }).click();
        await expect(customer.page.locator('#conversation-messages article[data-client-message-id]').getByText('Live inbox preview', { exact: true })).toBeVisible();

        await expect(seller.page.locator('[data-conversation-list] a').filter({ hasText: 'QA Canvas Tote' })).toContainText('Live inbox preview');
        await expect(seller.page.locator('[data-conversation-list] a').filter({ hasText: 'QA Canvas Tote' }).getByLabel(`${unreadBefore + 1} unread messages`)).toBeVisible();
        expect(await seller.page.evaluate(() => window.__chatPageMarker)).toBe(true);

        await seller.page.locator('[data-conversation-list] a').filter({ hasText: 'QA Canvas Tote' }).click();
        await expect(seller.page.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
        const conversationId = new URL(seller.page.url()).pathname.split('/').pop();
        await expect.poll(() => seller.page.evaluate((id) => window.Echo?.private(`conversation.${id}`).subscription.subscribed, conversationId)).toBe(true);
        await seller.page.evaluate(() => { window.__chatPageMarker = true; });
        await customer.page.getByRole('textbox', { name: 'Write a message' }).fill('Live thread update');
        await customer.page.getByRole('button', { name: 'Send message' }).click();
        await expect(customer.page.locator('#conversation-messages article[data-client-message-id]').getByText('Live thread update', { exact: true })).toBeVisible();
        await expect(seller.page.locator('#conversation-messages').getByText('Live thread update', { exact: true })).toBeVisible();
        expect(await seller.page.evaluate(() => window.__chatPageMarker)).toBe(true);
    } finally {
        await customer.context.close();
        await seller.context.close();
    }
});

test('seller quick reply fills the message draft without a server request', async ({ browser }) => {
    const customer = await openAsRole(browser, 'customer');

    try {
        await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await customer.page.getByRole('button', { name: 'Message seller' }).click();
        await expect(customer.page).toHaveURL(/\/messages\/[0-9A-HJKMNP-TV-Z]+$/i);
    } finally {
        await customer.context.close();
    }

    const seller = await openAsRole(browser, 'seller');

    try {
        await seller.page.goto('/messages');
        await rejectOptionalCookies(seller.page);
        await seller.page.locator('[data-conversation-list] a').filter({ hasText: 'QA Canvas Tote' }).click();
        await expect(seller.page.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
        await expect.poll(() => seller.page.evaluate(() => window.Echo?.connector.pusher.connection.state)).toBe('connected');
        await seller.page.waitForLoadState('networkidle');

        let updateRequests = 0;
        await seller.page.route(/\/livewire-[^/]+\/update$/, (route) => {
            updateRequests++;
            return route.abort();
        });
        const composer = seller.page.getByRole('textbox', { name: 'Write a message' });
        for (const reply of ['Yes, this item is available.', 'Your order is being prepared.', 'We’ll update you once your order ships.', 'Could you provide more details?']) {
            await seller.page.getByRole('button', { name: reply }).click();
            await expect(composer).toHaveValue(reply);
            await expect(seller.page.locator('#message-help')).toContainText(`${Array.from(reply).length}/2000`);

            // A quick reply raises no input event, so the box has to be told to grow or it clips.
            const clipped = await composer.evaluate((node) => node.scrollHeight - node.clientHeight);
            expect(clipped).toBeLessThanOrEqual(1);
        }
        expect(updateRequests).toBe(0);
    } finally {
        await seller.context.close();
    }
});

test('a quick reply that wraps stays fully visible in the composer on a phone', async ({ browser }) => {
    const customer = await openAsRole(browser, 'customer');

    try {
        await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await customer.page.getByRole('button', { name: 'Message seller' }).click();
        await expect(customer.page).toHaveURL(/\/messages\/[0-9A-HJKMNP-TV-Z]+$/i);
    } finally {
        await customer.context.close();
    }

    const seller = await openAsRole(browser, 'seller', { viewport: { width: 390, height: 844 } });

    try {
        await seller.page.goto('/messages');
        const conversationUrl = await seller.page.locator('[data-conversation-list] a').filter({ hasText: 'QA Canvas Tote' }).first().getAttribute('href');
        await seller.page.goto(conversationUrl, { waitUntil: 'domcontentloaded' });
        await rejectOptionalCookies(seller.page);
        const composer = seller.page.getByRole('textbox', { name: 'Write a message' });
        await expect(composer).toBeVisible();

        const reply = 'We’ll update you once your order ships.';
        await seller.page.getByRole('button', { name: reply }).click();
        await expect(composer).toHaveValue(reply);

        // The reply needs two lines at this width, and setting the body raises no input event, so
        // the box has to be told to grow or it hides the second line behind its own scroll.
        const composerBox = await composer.evaluate((node) => ({
            hidden: node.scrollHeight - node.clientHeight,
            lineHeight: parseFloat(getComputedStyle(node).lineHeight),
        }));

        // Anything approaching a whole line out of view means the box never grew.
        expect(composerBox.hidden).toBeLessThan(composerBox.lineHeight / 2);
    } finally {
        await seller.context.close();
    }
});

test('seller can send a drafted message after the Clerk cookie expires', async ({ browser }) => {
    const customer = await openAsRole(browser, 'customer');

    try {
        await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await customer.page.getByRole('button', { name: 'Message seller' }).click();
        await expect(customer.page).toHaveURL(/\/messages\/[0-9A-HJKMNP-TV-Z]+$/i);
    } finally {
        await customer.context.close();
    }

    const seller = await openAsRole(browser, 'seller');

    try {
        await seller.page.goto('/messages');
        await seller.page.locator('[data-conversation-list] a').filter({ hasText: 'QA Canvas Tote' }).click();
        const firstTyping = seller.page.waitForResponse((response) => /\/livewire-[^/]+\/update$/.test(new URL(response.url()).pathname));
        await seller.page.getByRole('textbox', { name: 'Write a message' }).fill('Your order is being prepared.');
        await firstTyping;
        await seller.context.clearCookies({ name: '__session' });
        await seller.context.setExtraHTTPHeaders({});
        await seller.page.evaluate(() => localStorage.setItem('clerk_session_expires_at', String(Date.now() - 1000)));

        const refresh = seller.page.waitForResponse((response) => response.url().endsWith('/auth/session') && response.request().method() === 'POST');
        await seller.page.getByRole('textbox', { name: 'Write a message' }).press('Enter');

        await expect((await refresh).ok()).toBeTruthy();
        await expect(seller.page).toHaveURL(/\/messages\/[0-9A-HJKMNP-TV-Z]+$/i);
        await expect(seller.page.locator('#conversation-messages').getByText('Your order is being prepared.', { exact: true })).toBeVisible();
        await expect(seller.page.getByRole('textbox', { name: 'Write a message' })).toBeEmpty();

        const secondTyping = seller.page.waitForResponse((response) => /\/livewire-[^/]+\/update$/.test(new URL(response.url()).pathname));
        await seller.page.getByRole('textbox', { name: 'Write a message' }).fill('Please check your order details.');
        await secondTyping;
        await seller.context.clearCookies({ name: '__session' });
        await seller.page.evaluate(() => localStorage.setItem('clerk_session_expires_at', String(Date.now() - 1000)));
        await seller.page.route('**/auth/session', (route) => route.fulfill({ status: 401, body: '{}' }));
        await seller.page.getByRole('button', { name: 'Send message' }).click();

        await expect(seller.page.getByRole('alert')).toContainText('Message could not be sent');
        await expect(seller.page.getByRole('textbox', { name: 'Write a message' })).toHaveValue('Please check your order details.');
        await expect(seller.page.locator('#conversation-messages article[data-client-message-id]').getByText('Please check your order details.', { exact: true })).toHaveCount(0);
        await expect(seller.page.locator('[data-pending-message-id]').filter({ hasText: 'Please check your order details.' })).toContainText('Failed');
        await expect(seller.page).toHaveURL(/\/messages\/[0-9A-HJKMNP-TV-Z]+$/i);
    } finally {
        await seller.context.close();
    }
});

test('chat message time follows the viewer local timezone', async ({ browser }) => {
    const timezoneId = new Date().getUTCHours() < 11 ? 'Etc/GMT+12' : 'Pacific/Kiritimati';
    const customer = await openAsRole(browser, 'customer', { timezoneId });

    try {
        await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await customer.page.getByRole('button', { name: 'Message seller' }).click();
        await expect(customer.page).toHaveURL(/\/messages\/[0-9A-HJKMNP-TV-Z]+$/i);
        await customer.page.reload();
        const typing = customer.page.waitForResponse((response) => /\/livewire-[^/]+\/update$/.test(new URL(response.url()).pathname));
        await customer.page.getByRole('textbox', { name: 'Write a message' }).fill('Local time check');
        await typing;
        await customer.page.getByRole('button', { name: 'Send message' }).click();

        const messageTime = customer.page.locator('#conversation-messages article[data-client-message-id]').filter({ hasText: 'Local time check' }).locator('time');
        await expect(messageTime).toBeVisible();
        const expectedTime = await messageTime.evaluate((element) => new Intl.DateTimeFormat('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        }).format(new Date(element.dateTime)));
        await expect(messageTime).toHaveText(expectedTime);

        const dateSeparator = customer.page.locator('#conversation-messages [data-chat-date-separator]:visible').last();
        const expectedDate = await messageTime.evaluate((element) => {
            const sentAt = new Date(element.dateTime);
            const today = new Date();
            const yesterday = new Date(today);
            yesterday.setDate(today.getDate() - 1);

            if (sentAt.toDateString() === today.toDateString()) return 'Today';
            if (sentAt.toDateString() === yesterday.toDateString()) return 'Yesterday';

            return new Intl.DateTimeFormat('en-US', { month: 'long', day: 'numeric', year: 'numeric' }).format(sentAt);
        });
        await expect(dateSeparator).toBeVisible();
        await expect(dateSeparator.locator('[data-chat-date-label]')).toHaveText(expectedDate);
    } finally {
        await customer.context.close();
    }
});

for (const attachment of [
    { label: 'photo', filename: 'chat-photo.png', mimeType: 'image/png', contents: 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/B9sAAAAASUVORK5CYII=' },
    { label: 'document', filename: 'chat-document.pdf', mimeType: 'application/pdf', contents: Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF') },
]) {
    test(`seller can upload a ${attachment.label} after the Clerk cookie expires`, async ({ browser }) => {
        const customer = await openAsRole(browser, 'customer');

        try {
            await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
            await customer.page.getByRole('button', { name: 'Message seller' }).click();
            await expect(customer.page).toHaveURL(/\/messages\/[0-9A-HJKMNP-TV-Z]+$/i);
        } finally {
            await customer.context.close();
        }

        const seller = await openAsRole(browser, 'seller');

        try {
            await seller.page.goto('/messages');
            await rejectOptionalCookies(seller.page);
            await seller.page.locator('[data-conversation-list] a').filter({ hasText: 'QA Canvas Tote' }).click();
            const rerender = seller.page.waitForResponse((response) => /\/livewire-[^/]+\/update$/.test(new URL(response.url()).pathname));
            await seller.page.getByRole('textbox', { name: 'Write a message' }).fill('Draft before uploading');
            await rerender;

            const fileChooser = seller.page.waitForEvent('filechooser');
            await seller.page.getByRole('button', { name: 'Add attachment' }).click();
            await (await fileChooser).setFiles({
                name: attachment.filename,
                mimeType: attachment.mimeType,
                buffer: typeof attachment.contents === 'string' ? Buffer.from(attachment.contents, 'base64') : attachment.contents,
            });
            await expect(seller.page.getByRole('dialog')).toHaveCount(0);
            await expect(seller.page.getByRole('status').filter({ hasText: attachment.filename })).toBeVisible();
            await expect(seller.page.getByRole('button', { name: 'Send message' })).toHaveCount(0);
            if (attachment.label === 'photo') {
                await seller.page.setViewportSize({ width: 390, height: 844 });
                const composerRow = await seller.page.evaluate(() => {
                    const selectors = ['button[aria-label="Add attachment"]', '#message-body', 'button[aria-label="Send photo"]'];
                    return selectors.map((selector) => document.querySelector(selector).getBoundingClientRect().bottom);
                });
                expect(Math.max(...composerRow) - Math.min(...composerRow)).toBeLessThan(3);
                await seller.page.getByRole('button', { name: 'Remove attachment' }).click();
                await expect(seller.page.getByRole('button', { name: 'Send message' })).toBeVisible();
                await seller.page.locator('#message-body').evaluate((messageBody, contents) => {
                    const bytes = Uint8Array.from(atob(contents), (character) => character.charCodeAt(0));
                    const transfer = new DataTransfer();
                    transfer.items.add(new File([bytes], 'chat-photo.png', { type: 'image/png' }));
                    messageBody.dispatchEvent(new DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer: transfer }));
                }, attachment.contents);
                await expect(seller.page.getByRole('status').filter({ hasText: attachment.filename })).toBeVisible();
            }
            await seller.context.clearCookies({ name: '__session' });
            await seller.context.setExtraHTTPHeaders({});
            await seller.page.evaluate(() => localStorage.setItem('clerk_session_expires_at', String(Date.now() - 1000)));

            const refresh = seller.page.waitForResponse((response) => response.url().endsWith('/auth/session') && response.request().method() === 'POST');
            await seller.page.getByRole('button', { name: `Send ${attachment.label}` }).click();

            await expect((await refresh).ok()).toBeTruthy();
            await expect(seller.page).toHaveURL(/\/messages\/[0-9A-HJKMNP-TV-Z]+$/i);
            const sentMessage = seller.page.locator('#conversation-messages article').filter({ hasText: attachment.filename }).last();
            await expect(sentMessage.getByText(attachment.filename, { exact: true })).toBeVisible();
            await expect(sentMessage.getByText('Draft before uploading', { exact: true })).toBeVisible();
            await expect(seller.page.getByRole('textbox', { name: 'Write a message' })).toBeEmpty();
        } finally {
            await seller.context.close();
        }
    });
}

test('an invalid chat file shows an error and can be replaced and retried', async ({ browser }) => {
    const customer = await openAsRole(browser, 'customer');
    try {
        await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await customer.page.getByRole('button', { name: 'Message seller' }).click();
    } finally {
        await customer.context.close();
    }

    const seller = await openAsRole(browser, 'seller');
    try {
        await seller.page.goto('/messages');
        await rejectOptionalCookies(seller.page);
        await seller.page.locator('[data-conversation-list] a').filter({ hasText: 'QA Canvas Tote' }).click();
        await seller.page.getByRole('textbox', { name: 'Write a message' }).fill('Here is the receipt.');
        await seller.page.getByRole('button', { name: 'Add attachment' }).click();
        await seller.page.getByLabel('Image or PDF document').setInputFiles({ name: 'first.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n%%EOF') });
        await expect(seller.page.getByRole('status').filter({ hasText: 'first.pdf' })).toBeVisible();

        // A kind the server would never take is refused beside the composer, before anything is sent.
        await seller.page.getByLabel('Image or PDF document').setInputFiles({ name: 'notes.txt', mimeType: 'text/plain', buffer: Buffer.from('not a PDF') });
        await expect(seller.page.getByRole('dialog')).toHaveCount(0);
        await expect(seller.page.getByRole('alert')).toContainText('Choose a JPG, PNG, WebP or PDF file.');
        await expect(seller.page.getByRole('status').filter({ hasText: 'first.pdf' })).toHaveCount(0);
        await expect(seller.page.getByRole('button', { name: 'Send message' })).toBeVisible();
        await expect(seller.page.getByRole('status').filter({ hasText: 'notes.txt' })).toHaveCount(0);

        // One the server alone can see through — an image header over contents that are not an
        // image — still has to come back as a refusal the composer can recover from.
        await seller.page.getByLabel('Image or PDF document').setInputFiles({ name: 'disguised.png', mimeType: 'image/png', buffer: Buffer.from('not an image') });

        await seller.page.route(/\/messages\/[^/]+\/attachments$/, async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 500));
            await route.continue();
        }, { times: 1 });
        const rejected = seller.page.waitForResponse((response) => response.url().includes('/attachments') && response.request().method() === 'POST');
        await seller.page.getByRole('button', { name: 'Send photo' }).click();
        await expect(seller.page.getByRole('progressbar', { name: 'Attachment upload progress' })).toBeVisible();
        expect((await rejected).status()).toBe(422);
        await expect(seller.page.getByRole('alert')).toContainText('attachment');
        await expect(seller.page.getByRole('textbox', { name: 'Write a message' })).toHaveValue('Here is the receipt.');
        await expect(seller.page.getByRole('button', { name: 'Send photo' })).toBeEnabled();

        await seller.page.getByRole('button', { name: 'Add attachment' }).click();
        await seller.page.getByLabel('Image or PDF document').setInputFiles({ name: 'receipt.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF') });
        await expect(seller.page.getByRole('status').filter({ hasText: 'receipt.pdf' })).toContainText('B');
        await seller.page.getByRole('button', { name: 'Send document' }).click();
        const message = seller.page.locator('#conversation-messages article').filter({ hasText: 'receipt.pdf' }).last();
        await expect(message.getByText('Here is the receipt.', { exact: true })).toBeVisible();
        await expect(message.getByRole('link', { name: 'Open' })).toBeVisible();
        await expect(message.getByRole('link', { name: 'Download' })).toBeVisible();
    } finally {
        await seller.context.close();
    }
});

test('public product page has no automated WCAG A or AA violations', async ({ page }) => {
    await page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .analyze();

    expect(results.violations).toEqual([]);
});

test('messaging workspace has no browser exceptions or automated WCAG A or AA violations', async ({ browser }) => {
    const customer = await openAsRole(browser, 'customer');
    const exceptions = [];
    customer.page.on('pageerror', (error) => exceptions.push(error.message));

    try {
        await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await customer.page.getByRole('button', { name: 'Message seller' }).click();
        await expect(customer.page.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
        const results = await new AxeBuilder({ page: customer.page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
            .analyze();

        expect(results.violations).toEqual([]);
        expect(exceptions).toEqual([]);
    } finally {
        await customer.context.close();
    }
});

test('marketplace document responds within the local performance budget', async ({ page }) => {
    const response = await page.goto('/', { waitUntil: 'domcontentloaded' });

    expect(response?.ok()).toBe(true);
    const domContentLoaded = await page.evaluate(() => performance.getEntriesByType('navigation')[0]?.domContentLoadedEventEnd ?? Infinity);
    expect(domContentLoaded).toBeLessThan(8_000);
});
