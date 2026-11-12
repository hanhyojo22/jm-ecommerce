import { expect, test } from '@playwright/test';
import { openAsRole } from './support/role-page.js';

async function rejectOptionalCookies(page) {
    const reject = page.locator('[data-cookie-banner] [data-cookie-action="reject"]');
    if (await reject.isVisible()) {
        await Promise.all([page.waitForEvent('domcontentloaded'), reject.click()]);
        await expect(page.locator('[data-cookie-banner]')).toHaveCount(0);
    }
}

async function reviewAndSend(page) {
    await page.getByRole('button', { name: 'Review and send' }).click();
    const review = page.getByRole('dialog', { name: 'Review before sending' });
    await expect(review).toBeVisible();
    const send = review.getByRole('button', { name: 'Send notification' });
    await expect(send).toBeDisabled();
    await review.getByLabel('I checked the preview and want to send this now.').check();
    await send.click();
}

test('admin sends a notification to one customer and it opens its link from the bell', async ({ browser }) => {
    const admin = await openAsRole(browser, 'admin');
    const customer = await openAsRole(browser, 'customer');
    const title = `Weekend sale ${Date.now()}`;

    try {
        await admin.page.goto('/admin/announcements');
        await rejectOptionalCookies(admin.page);
        await expect(admin.page.getByRole('heading', { name: 'Notifications', level: 1 })).toBeVisible();

        await admin.page.getByRole('radio', { name: 'One customer' }).check();
        await admin.page.getByLabel('Customer email').fill('QA-Customer@example.test');
        await admin.page.getByRole('radio', { name: 'Other page' }).click();
        await admin.page.getByRole('button', { name: 'Cart', exact: true }).click();
        await admin.page.getByLabel('Title').fill(title);
        await admin.page.getByLabel('Message').fill('Everything in your cart is 10% off until Sunday.');
        await reviewAndSend(admin.page);

        await expect(admin.page.getByText('Notification queued for 1 recipient(s).', { exact: true })).toBeVisible();
        await expect(admin.page).toHaveURL(/tab=history/);
        // The e2e queue is sync, so the send has already finished by the time the page reloads.
        const sent = admin.page.locator('li', { hasText: title });
        await expect(sent).toContainText('qa-customer@example.test');
        await expect(sent).toContainText('1/1 sent');
        await expect(sent).toContainText('Sent');
        await expect(sent.getByRole('button', { name: 'Cancel' })).toHaveCount(0);

        await customer.page.goto('/orders');
        await rejectOptionalCookies(customer.page);
        await customer.page.getByRole('button', { name: /^Notifications, \d+ unread$/ }).click();
        const item = customer.page.locator('[data-notification-bell] button', { hasText: title });
        await expect(item).toContainText('Everything in your cart is 10% off until Sunday.');
        await item.click();
        await expect(customer.page).toHaveURL(/\/cart$/);
    } finally {
        await admin.context.close();
        await customer.context.close();
    }
});

test('admin starts a notification from a ready-made template', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'admin');

    try {
        await page.goto('/admin/announcements');
        await rejectOptionalCookies(page);

        const productAd = page.getByRole('button', { name: 'Product ad' });
        await productAd.click();

        await expect(productAd).toHaveAttribute('aria-pressed', 'true');
        await expect(page.getByLabel('Title')).toHaveValue('Fresh finds just landed');
        await expect(page.getByLabel('Message')).toHaveValue('Discover new arrivals from JMMart sellers. Browse the latest products and find something to love.');
        await expect(page.getByRole('radio', { name: 'Other page' })).toHaveAttribute('aria-checked', 'true');
        await expect(page.getByLabel('JMMart link')).toHaveValue(new URL('/search', page.url()).href);

        await page.getByLabel('Title').fill('Custom title');
        await expect(productAd).toHaveAttribute('aria-pressed', 'false');
    } finally {
        await context.close();
    }
});

test('admin picks what the notification opens, searching only that kind', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'admin');

    try {
        await page.goto('/admin/announcements');
        await rejectOptionalCookies(page);

        const link = page.locator('input[name="url"]');
        const opensRow = page.locator('aside dl div', { hasText: 'Opens' });

        await page.getByRole('radio', { name: 'A store' }).click();
        await page.getByRole('combobox', { name: 'Store', exact: true }).fill('northwind');
        await page.getByRole('option', { name: /QA Northwind Store/ }).click();
        await expect(page.getByRole('option', { name: /QA Canvas Tote/ })).toHaveCount(0);
        await expect(link).toHaveValue(new URL('/stores/qa-northwind-store', page.url()).href);
        await expect(opensRow).toContainText('Store · QA Northwind Store');

        await page.getByRole('radio', { name: 'A product' }).click();
        await expect(link).toHaveValue('');
        await page.getByRole('combobox', { name: 'From store (optional)' }).fill('southwind');
        await page.getByRole('option', { name: /QA Southwind Store/ }).click();
        const product = page.getByRole('combobox', { name: 'Product' });
        await product.fill('canvas');
        await expect(page.getByText('No product in QA Southwind Store matches “canvas”.')).toBeVisible();
        await page.getByRole('button', { name: 'Search every store' }).click();
        await expect(page.getByRole('option', { name: /QA Canvas Tote/ })).toContainText('₱19.99');
        await product.press('ArrowDown');
        await product.press('ArrowUp');
        await product.press('Enter');
        await expect(link).toHaveValue(new URL('/stores/qa-northwind-store/products/qa-canvas-tote', page.url()).href);
        await expect(opensRow).toContainText('Product · QA Canvas Tote');

        await page.getByRole('button', { name: 'Change product' }).click();
        await expect(link).toHaveValue('');
        await expect(opensRow).toContainText('Home');

        await page.getByLabel('Title').fill('Draft');
        await page.getByLabel('Message').fill('Draft body');
        await page.getByRole('button', { name: 'Review and send' }).click();
        await expect(page.getByText('Choose a product, or switch to Home.')).toBeVisible();
        await expect(page.getByRole('dialog', { name: 'Review before sending' })).toBeHidden();
    } finally {
        await context.close();
    }
});

test('picking a product uses its photo until the admin sets a custom image', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'admin');

    try {
        await page.goto('/admin/announcements');
        await rejectOptionalCookies(page);

        const imageUrl = page.getByLabel('Or paste an image URL');
        const imageRow = page.locator('aside dl div', { hasText: 'Image' });
        const product = page.getByRole('combobox', { name: 'Product' });
        const pick = async (query, name) => {
            await product.fill(query);
            await page.getByRole('option', { name }).click();
        };

        await page.getByRole('radio', { name: 'A product' }).click();
        await pick('speaker', /QA Variant Speaker/);
        await expect(imageUrl).toHaveValue(/qa\/variant-/);
        await expect(page.getByText('Using product photo')).toBeVisible();
        await expect(page.getByLabel('Title')).toHaveValue(/^QA Variant Speaker · ₱10\.00$/);
        await expect(imageRow).toContainText('Product photo');

        await imageUrl.fill('https://example.com/sale-banner.jpg');
        await expect(imageRow).toContainText('Custom image');

        await page.getByRole('button', { name: 'Change product' }).click();
        await pick('other seller', /Other Seller QA Product/);
        await expect(imageUrl).toHaveValue('https://example.com/sale-banner.jpg');

        await page.getByRole('button', { name: 'Change product' }).click();
        await pick('speaker', /QA Variant Speaker/);
        await page.getByRole('button', { name: 'Use product photo' }).click();
        await expect(imageUrl).toHaveValue(/qa\/variant-/);

        await page.getByRole('button', { name: 'Change product' }).click();
        await expect(imageUrl).toHaveValue('');
        await expect(imageRow).toContainText('None');
    } finally {
        await context.close();
    }
});

test('admin uploads a campaign image and sees it across the composer previews', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'admin');

    try {
        await page.goto('/admin/announcements');
        await rejectOptionalCookies(page);

        await page.locator('#campaign_image').setInputFiles({
            name: 'campaign.png',
            mimeType: 'image/png',
            buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64'),
        });

        await expect(page.getByAltText('Campaign image preview')).toHaveCount(2);
        await expect(page.locator('aside dl div', { hasText: 'Image' })).toContainText('Custom image');
        await expect(page.getByRole('button', { name: 'Replace', exact: true })).toBeVisible();

        await page.getByRole('button', { name: 'Remove', exact: true }).click();
        await expect(page.getByAltText('Campaign image preview')).toHaveCount(0);
        await expect(page.locator('aside dl div', { hasText: 'Image' })).toContainText('None');
    } finally {
        await context.close();
    }
});

test('admin cannot send a notification that links to another site or an unknown customer', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'admin');

    try {
        await page.goto('/admin/announcements');
        await rejectOptionalCookies(page);

        await page.getByLabel('Title').fill('Phishing check');
        await page.getByLabel('Message').fill('This must not be sent.');
        await page.getByRole('radio', { name: 'Other page' }).click();
        await page.getByLabel('JMMart link').fill('https://example.com/login');
        await reviewAndSend(page);
        // The layout also lists errors in a summary; the field's own message sits inside the form.
        await expect(page.locator('form').getByText('The link must be a JMMart page.')).toBeVisible();

        // Confirming once and going back to edit must not let Enter skip a second review.
        await page.getByLabel('JMMart link').fill('');
        await page.getByRole('radio', { name: 'Home' }).click();
        await page.getByRole('button', { name: 'Review and send' }).click();
        const review = page.getByRole('dialog', { name: 'Review before sending' });
        await review.getByLabel('I checked the preview and want to send this now.').check();
        await review.getByRole('button', { name: 'Back to editing' }).click();
        await page.getByLabel('Title').fill('Phishing check edited');
        await page.getByLabel('Title').press('Enter');
        await expect(page).not.toHaveURL(/tab=history/);
        await page.getByRole('button', { name: 'Review and send' }).click();
        await expect(review.getByRole('button', { name: 'Send notification' })).toBeDisabled();
        await review.getByRole('button', { name: 'Back to editing' }).click();

        await page.getByRole('radio', { name: 'One customer' }).check();
        await page.getByLabel('Customer email').fill('nobody@example.test');
        await page.getByRole('radio', { name: 'Home' }).click();
        await reviewAndSend(page);
        await expect(page.locator('form').getByText('No active customer has this email.')).toBeVisible();

        await page.goto('/admin/announcements?tab=history');
        await expect(page.locator('li', { hasText: 'Phishing check' })).toHaveCount(0);
    } finally {
        await context.close();
    }
});

test('customer turns cart reminders off and back on from account settings', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');

    try {
        await page.goto('/profile');
        await rejectOptionalCookies(page);
        const reminders = page.getByRole('switch', { name: /Cart reminders/ });
        await expect(reminders).toBeChecked();

        await Promise.all([page.waitForEvent('domcontentloaded'), reminders.click()]);
        await expect(page.getByText('Notification settings saved.', { exact: true })).toBeVisible();
        await expect(page.getByRole('switch', { name: /Cart reminders/ })).not.toBeChecked();

        await Promise.all([page.waitForEvent('domcontentloaded'), page.getByRole('switch', { name: /Cart reminders/ }).click()]);
        await expect(page.getByRole('switch', { name: /Cart reminders/ })).toBeChecked();
    } finally {
        await context.close();
    }
});

test('customer adds, edits, cancels and removes a delivery address', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');
    const recipient = `QA Address ${Date.now()}`;

    try {
        await page.goto('/delivery-addresses');
        await rejectOptionalCookies(page);
        await expect(page.getByRole('heading', { name: 'Delivery addresses' })).toBeVisible();

        await page.getByLabel('Recipient name').fill(recipient);
        await page.getByLabel('Mobile number').fill('09171234567');
        await page.getByLabel('Street address').fill('12 Mabini Street, Barangay San Antonio');
        await page.getByLabel('City').fill('Pasig');
        await page.getByLabel('Province').fill('Metro Manila');
        await page.getByLabel('Postal code').fill('1600');
        await page.getByRole('button', { name: 'Save address' }).click();
        await expect(page.getByText('Delivery address saved.', { exact: true })).toBeVisible();
        await expect(page.getByText('12 Mabini Street, Barangay San Antonio, Pasig, Metro Manila 1600', { exact: true })).toBeVisible();

        await page.getByRole('link', { name: `Edit address for ${recipient}` }).click();
        await expect(page.getByRole('heading', { name: 'Edit delivery address' })).toBeVisible();
        await page.getByLabel('City').fill('Taguig');
        await page.getByRole('link', { name: 'Cancel' }).click();
        await expect(page).toHaveURL(/\/delivery-addresses$/);
        await expect(page.getByText('12 Mabini Street, Barangay San Antonio, Pasig, Metro Manila 1600', { exact: true })).toBeVisible();

        await page.getByRole('link', { name: `Edit address for ${recipient}` }).click();
        await expect(page.getByRole('heading', { name: 'Edit delivery address' })).toBeVisible();
        await page.getByLabel('City').fill('Taguig');
        await page.getByRole('button', { name: 'Save changes', exact: true }).click();
        await expect(page.getByText('12 Mabini Street, Barangay San Antonio, Taguig, Metro Manila 1600', { exact: true })).toBeVisible();

        page.once('dialog', (dialog) => dialog.accept());
        await page.getByRole('button', { name: `Remove address for ${recipient}` }).click();
        await expect(page.getByRole('button', { name: `Remove address for ${recipient}` })).toHaveCount(0);
    } finally {
        await context.close();
    }
});

test('customer account pages show purchase and review details', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');

    try {
        await page.goto('/orders');
        await rejectOptionalCookies(page);
        const reviewedOrder = page.locator('[data-order-number="QA-REVIEW-001"]');
        await expect(reviewedOrder).toContainText('QA Northwind Store');
        await expect(reviewedOrder).toContainText('Cash on delivery');
        await expect(reviewedOrder).toContainText('Delivered');

        await page.goto('/my-reviews');
        await expect(page.getByRole('heading', { name: 'My reviews' })).toBeVisible();
        await expect(page.locator('p', { hasText: 'average rating' })).toContainText(/\d+ reviews?/);
        const review = page.locator('article', { hasText: 'QA Canvas Tote' }).first();
        await expect(review).toContainText('Published');
        await expect(review).toContainText('Verified purchase');
        await expect(review).toContainText('QA-REVIEW-001');
        await expect(review.getByRole('img', { name: /out of 5 stars/ })).toBeVisible();
        await review.getByRole('link', { name: 'View purchase' }).click();
        await expect(page).toHaveURL(/\/orders\//);
    } finally {
        await context.close();
    }
});
