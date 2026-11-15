import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { openAsRole } from './support/role-page.js';

test('buyer, seller, and support complete a return case', async ({ browser }) => {
    test.setTimeout(90_000);
    const buyer = await openAsRole(browser, 'customer');
    try {
        await buyer.page.goto('/orders');
        await rejectOptionalCookies(buyer.page);
        // The card link opens a quick-view modal; the return form lives on the full order page.
        await buyer.page.goto(await buyer.page.locator('article[data-order-number="QA-REVIEW-001"] > a').getAttribute('href'));
        const returns = buyer.page.locator('#returns');
        await returns.locator('summary').click();
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
        await page.getByRole('radio', { name: /^QA Seller 20 QA Street, Manila/ }).check();
        await expect(page.getByLabel('Street address')).toHaveValue('20 QA Street');
        await expect(page.getByLabel('Province')).toHaveValue('Metro Manila');
        await expect(page.getByLabel('Postal code')).toHaveValue('1000');
        await page.getByRole('button', { name: 'Continue to payment' }).click();
        await page.locator('#checkout-form').getByRole('button', { name: 'Review order' }).click();
        await page.locator('#checkout-form').getByRole('button', { name: /Place order/ }).click();
        await expect(page).toHaveURL(/\/orders\/[0-9A-HJKMNP-TV-Z]+\/placed$/i);
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
        await page.getByRole('button', { name: 'Continue to payment' }).click();
        await expect(page.locator('#step-1-title')).toContainText('Ava QA Customer · 10 QA Street · Manila');
        await page.locator('#checkout-form').getByRole('button', { name: 'Review order' }).click();
        await expect(page.locator('#step-3-body')).toContainText('10 QA Street, Manila, Metro Manila, 1000');
        await page.getByLabel('Delivery note').fill('Blue gate');
        const deliveryBox = await page.locator('#step-1-title').boundingBox();
        const summaryBox = await page.locator('#checkout-summary-heading').boundingBox();
        expect(summaryBox.x).toBeGreaterThan(deliveryBox.x);
        expect(await page.locator('#checkout-summary-heading').locator('xpath=ancestor::aside').evaluate(element => getComputedStyle(element).position)).toBe('sticky');
        await page.screenshot({ path: testInfo.outputPath('checkout-desktop.png'), fullPage: true });
        await page.locator('#checkout-form').getByRole('button', { name: /Place order/ }).click();

        await expect(page).toHaveURL(/\/orders\/[0-9A-HJKMNP-TV-Z]+\/placed$/i);
        await page.getByRole('link', { name: /Track order/ }).click();
        await expect(page).toHaveURL(/\/orders\/[0-9A-HJKMNP-TV-Z]+$/i);
        await expect(page.getByRole('heading', { name: 'Order details' })).toBeVisible();
        await expect(page.getByText('QA Canvas Tote')).toBeVisible();
        await expect(page.getByText('Blue gate')).toBeVisible();
        await expect(page.getByRole('region', { name: 'Order summary' })).toContainText('Cash on delivery');
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

        const form = page.locator('#checkout-form');
        const summary = page.getByRole('button', { name: /^Order summary/ });
        expect((await summary.boundingBox()).y).toBeLessThan((await page.locator('#step-1-title').boundingBox()).y);
        await summary.click();
        await expect(page.locator('#checkout-summary-body')).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('checkout-mobile.png'), fullPage: true });
        const newAddress = page.getByRole('radio', { name: /Use a new address/ });
        if (await newAddress.count()) {
            await newAddress.check();
        }
        await page.getByLabel('Recipient name').fill('');
        await page.getByLabel('Mobile number').fill('');
        await page.getByLabel('Street address').fill('');
        await page.getByLabel('City').fill('');
        await form.evaluate(element => { element.noValidate = true; });
        await page.getByRole('button', { name: 'Continue', exact: true }).click();
        await page.getByRole('button', { name: 'Review order', exact: true }).click();
        await expect(page.getByRole('button', { name: /Place order/ })).toHaveCount(1);
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

test('seller refreshes an expired session before posting a review response', async ({ browser }, testInfo) => {
    const { context, page } = await openAsRole(browser, 'seller');

    try {
        await page.goto('/seller/reviews');
        await rejectOptionalCookies(page);
        await expect(page.getByLabel('Seller response')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Reply', exact: true })).toHaveCount(0);
        for (const width of [1440, 390]) {
            await page.setViewportSize({ width, height: 900 });
            await page.locator('[data-review-thread]').first().screenshot({ path: testInfo.outputPath(`inline-review-reply-${width}.png`) });
        }
        const editResponse = page.locator('[data-seller-reviews] [data-review-thread]').first().getByRole('button', { name: 'Edit response' });
        if (await editResponse.isVisible()) await editResponse.click();
        await page.getByLabel('Seller response').fill('Thank you for your feedback.');
        const refresh = page.waitForResponse((response) => response.url().endsWith('/auth/session') && response.request().method() === 'POST');
        await page.getByRole('button', { name: /^(Send|Update) response$/ }).click();

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
    let finishSubmission = () => {};
    for (const page of [seller.page, buyer.page]) page.on('pageerror', (error) => pageErrors.push(error.message));

    try {
        await seller.page.goto('/seller/reviews');
        await rejectOptionalCookies(seller.page);
        const sellerThread = seller.page.locator('[data-seller-reviews] [data-review-thread]').filter({ hasText: 'QA Canvas Tote' }).first();
        const responseButton = sellerThread.getByRole('button', { name: 'Edit response' });
        const alreadyResponded = await responseButton.isVisible();
        if (alreadyResponded) await responseButton.click();
        await expect(sellerThread.getByLabel('Seller response')).toBeVisible();
        await sellerThread.getByLabel('Seller response').fill('Thanks for reviewing the tote.');
        await sellerThread.getByRole('button', { name: alreadyResponded ? 'Update response' : 'Send response' }).click();
        await expect(sellerThread).toContainText('Thanks for reviewing the tote.');
        await expect(sellerThread.getByLabel('Seller response')).toBeHidden();

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
        const pendingSubmission = new Promise((resolve) => { finishSubmission = resolve; });
        await buyer.page.route(/\/livewire[^/]*\/update$/, async (route) => {
            await pendingSubmission;
            await route.continue();
        });
        const sendReply = storeResponse.getByRole('button', { name: 'Send' });
        await sendReply.click();
        await expect(sendReply).toBeDisabled();
        const optimisticReply = thread.locator('[data-pending-reply]').filter({ hasText: 'Does it come in blue?' });
        await expect(optimisticReply).toBeVisible();
        await expect(optimisticReply).toContainText('Sending...');
        finishSubmission();
        await expect(thread).toContainText('Does it come in blue?');
        await expect(thread.locator('[data-pending-reply]')).toHaveCount(0);
        await buyer.page.unroute(/\/livewire[^/]*\/update$/);
        await expect(buyer.page).toHaveURL(/\/stores\/qa-northwind-store\/products\/qa-canvas-tote/);
        await thread.getByRole('button', { name: 'Hide replies' }).click();
        await expect(thread).not.toContainText('Does it come in blue?');
        await thread.getByRole('button', { name: /View replies/ }).click();
        await expect(thread).toContainText('Does it come in blue?');

        await seller.page.reload();
        await seller.page.route(/\/livewire[^/]*\/update$/, (route) => route.abort());
        const sellerReviewThread = seller.page.locator('#reviews article').first();
        await sellerReviewThread.getByText('Reply', { exact: true }).click();
        await expect(seller.page.getByLabel('Write a reply')).toBeVisible();
        await seller.page.unroute(/\/livewire[^/]*\/update$/);
        await seller.page.getByLabel('Write a reply').fill('Yes, blue is available.');
        await seller.page.getByRole('button', { name: 'Post reply' }).click();
        await expect(sellerReviewThread).toContainText('Yes, blue is available.');
        await expect(sellerReviewThread.locator('[data-pending-reply]')).toHaveCount(0);
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
        await expect(thread.locator('[data-pending-reply]')).toHaveCount(0);

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
        finishSubmission();
        await seller.context.close();
        await buyer.context.close();
    }
});

test('seller response stays on the form when session renewal fails', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'seller');

    try {
        await page.goto('/seller/reviews');
        await rejectOptionalCookies(page);
        const editResponse = page.locator('[data-seller-reviews] [data-review-thread]').first().getByRole('button', { name: 'Edit response' });
        if (await editResponse.isVisible()) await editResponse.click();

        const composer = page.getByLabel('Seller response');
        await expect(composer).toBeVisible();

        await page.route('**/auth/session', (route) => route.fulfill({ status: 401, body: 'Session expired' }));
        await composer.fill('Please keep this draft.');
        await page.getByRole('button', { name: /^(Send|Update) response$/ }).click();

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
    let finishReply = () => {};

    try {
        await seller.page.goto('/seller/store-settings');
        await rejectOptionalCookies(seller.page);
        await seller.page.getByLabel('Change logo').setInputFiles('public/images/categories/electronics.png');
        await seller.page.getByRole('button', { name: 'Save store settings' }).click();
        await expect(seller.page.getByText('Branch details saved.')).toBeVisible();

        await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote#discussions');
        await rejectOptionalCookies(customer.page);
        await customer.page.locator('#discussion-body').fill('Does this tote come in blue?');
        await customer.page.getByRole('button', { name: 'Post message' }).click();
        await expect(customer.page.locator('#discussions')).toContainText('Does this tote come in blue?');

        await seller.page.goto('/seller/reviews');
        await expect(seller.page.getByRole('heading', { name: 'Reviews & conversations' })).toBeVisible();
        await expect(seller.page.locator('.seller-review-score')).toContainText('5.0');
        await seller.page.getByRole('tab', { name: 'Product conversations' }).click();
        await expect(seller.page.locator('[data-seller-reviews]')).toContainText('Does this tote come in blue?');
        await expect(seller.page.locator('.seller-review-discussion').getByLabel('Your reply').first()).toBeVisible();
        await expect(seller.page.locator('.seller-review-discussion details')).toHaveCount(0);
        expect(await seller.page.locator('.seller-review-discussion').getByRole('button', { name: 'Send reply' }).first().evaluate((button) => getComputedStyle(button).backgroundColor)).toBe('rgba(0, 0, 0, 0)');
        await seller.page.locator('.seller-review-discussion').getByLabel('Your reply').first().fill('Yes, blue is available.');
        const replyRequests = [];
        seller.page.on('requestfinished', (request) => {
            if (request.url().includes('/discussions') || request.url().includes('/seller/reviews')) {
                replyRequests.push(new URL(request.url()).pathname);
            }
        });
        const replyPending = new Promise((resolve) => { finishReply = resolve; });
        await seller.page.route('**/products/*/discussions', async (route) => {
            await replyPending;
            await route.continue();
        });
        await seller.page.locator('.seller-review-discussion').getByRole('button', { name: 'Send reply' }).first().click();
        const optimisticSellerReply = seller.page.locator('[data-pending-seller-reply]').filter({ hasText: 'Yes, blue is available.' });
        await expect(optimisticSellerReply).toBeVisible();
        await expect(optimisticSellerReply).toContainText('Sending...');
        finishReply();
        await expect(seller.page).toHaveURL(/\/seller\/reviews$/);
        await expect(seller.page.locator('[data-seller-reviews]')).toContainText('Yes, blue is available.');
        await expect(optimisticSellerReply).toHaveCount(0);
        await seller.page.unroute('**/products/*/discussions');
        const storeAvatar = seller.page.locator('.seller-review-discussion .seller-review-avatar-image').first();
        await expect(storeAvatar).toBeVisible();
        await expect.poll(() => storeAvatar.evaluate((image) => image.complete && image.naturalWidth > 0)).toBe(true);
        expect(replyRequests).toContainEqual(expect.stringMatching(/\/discussions$/));
        expect(replyRequests).not.toContain('/seller/reviews');
        const followupForm = seller.page.locator('.seller-review-discussion [data-discussion-reply-form]').last();
        await followupForm.getByLabel('Your reply').fill('Please keep this unsent draft.');
        await seller.page.route('**/products/*/discussions', (route) => route.abort());
        await followupForm.getByRole('button', { name: 'Send reply' }).click();
        await expect(followupForm.locator('[data-discussion-feedback]')).toBeVisible();
        await expect(followupForm.getByLabel('Your reply')).toHaveValue('Please keep this unsent draft.');
        await expect(followupForm.getByRole('button', { name: 'Send reply' }).locator('svg')).toHaveCount(1);
        await expect(seller.page.locator('[data-pending-seller-reply]')).toHaveCount(0);
        await seller.page.getByRole('button', { name: 'Dismiss', exact: true }).click();
        await seller.page.unroute('**/products/*/discussions');
        await seller.page.locator('.seller-review-discussion').getByLabel('Your reply').last().fill('Any shade of blue works.');
        await seller.page.locator('.seller-review-discussion').getByRole('button', { name: 'Send reply' }).last().click();
        await expect(seller.page.locator('[data-seller-reviews]')).toContainText('Any shade of blue works.');
        expect(replyRequests).not.toContain('/seller/reviews');

        for (const width of [1440, 390]) {
            await seller.page.setViewportSize({ width, height: 900 });
            await seller.page.evaluate(() => window.scrollTo(0, 0));
            await seller.page.getByRole('tab', { name: 'Product reviews' }).click();
            await seller.page.screenshot({ path: testInfo.outputPath(`seller-reviews-reviews-${width}.png`), fullPage: true });
            await seller.page.getByRole('tab', { name: 'Product conversations' }).click();
            await seller.page.screenshot({ path: testInfo.outputPath(`seller-reviews-conversations-${width}.png`), fullPage: true });
            const sizes = await seller.page.evaluate(() => ({ viewport: document.documentElement.clientWidth, page: document.documentElement.scrollWidth }));
            expect(sizes.page, `${width}px seller reviews`).toBeLessThanOrEqual(sizes.viewport);
        }

        await seller.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote#discussions');
        await expect(seller.page.locator('#discussions .pd-discussion .pd-avatar.seller img').first()).toBeVisible();
        await expect(seller.page.locator('#discussions .pd-comment-form > .pd-avatar.seller img')).toBeVisible();
        await seller.page.locator('#discussions .pd-discussion-reply summary').first().click();
        expect(await seller.page.locator('#discussions .pd-reply-submit').first().evaluate((button) => getComputedStyle(button).backgroundColor)).toBe('rgba(0, 0, 0, 0)');
    } finally {
        finishReply();
        await seller.context.close();
        await customer.context.close();
    }
});

test('product comments appear optimistically before the server confirms', async ({ browser }) => {
    const customer = await openAsRole(browser, 'customer');
    const body = `Optimistic product comment ${Date.now()}`;
    let finishSubmission = () => {};

    try {
        await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote#discussions');
        await rejectOptionalCookies(customer.page);
        await customer.page.locator('#discussion-body').fill(body);

        const pendingSubmission = new Promise((resolve) => { finishSubmission = resolve; });
        await customer.page.route('**/products/*/discussions', async (route) => {
            await pendingSubmission;
            await route.continue();
        });

        await customer.page.getByRole('button', { name: 'Post message' }).click();

        const optimisticComment = customer.page.locator('[data-pending-discussion]').filter({ hasText: body });
        await expect(optimisticComment).toBeVisible();
        await expect(optimisticComment).toContainText('Sending...');
        expect(await customer.page.locator('#discussion-body').inputValue()).toBe('');

        finishSubmission();

        await expect(customer.page.locator('[data-pending-discussion]')).toHaveCount(0);
        await expect(customer.page.locator('#discussions article').filter({ hasText: body })).toBeVisible();
    } finally {
        finishSubmission();
        await customer.context.close();
    }
});

test('failed product comment restores the draft and removes the pending message', async ({ browser }) => {
    const customer = await openAsRole(browser, 'customer');
    const body = `Unsent product comment ${Date.now()}`;

    try {
        await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote#discussions');
        await rejectOptionalCookies(customer.page);
        await customer.page.route('**/products/*/discussions', (route) => route.abort());

        await customer.page.locator('#discussion-body').fill(body);
        await customer.page.getByRole('button', { name: 'Post message' }).click();

        await expect(customer.page.locator('[data-pending-discussion]')).toHaveCount(0);
        await expect(customer.page.locator('#discussion-body')).toHaveValue(body);
        await expect(customer.page.locator('[data-discussion-feedback]').filter({ hasText: 'Could not confirm your message' })).toBeVisible();
    } finally {
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
