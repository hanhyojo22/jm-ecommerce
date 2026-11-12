import { expect, test } from '@playwright/test';
import { openAsRole } from './support/role-page.js';

async function rejectOptionalCookies(page) {
    const reject = page.locator('[data-cookie-banner] [data-cookie-action="reject"]');
    if (await reject.isVisible()) {
        await Promise.all([page.waitForEvent('domcontentloaded'), reject.click()]);
        await expect(page.locator('[data-cookie-banner]')).toHaveCount(0);
    }
}

test('admin sends a notification to one customer and it opens its link from the bell', async ({ browser }) => {
    const admin = await openAsRole(browser, 'admin');
    const customer = await openAsRole(browser, 'customer');
    const title = `Weekend sale ${Date.now()}`;

    try {
        await admin.page.goto('/admin/announcements');
        await rejectOptionalCookies(admin.page);
        await expect(admin.page.getByRole('heading', { name: 'Send a notification' })).toBeVisible();

        await admin.page.getByRole('radio', { name: 'One customer' }).check();
        await admin.page.getByLabel('Customer email').fill('QA-Customer@example.test');
        await admin.page.getByLabel('Title').fill(title);
        await admin.page.getByLabel('Message').fill('Everything in your cart is 10% off until Sunday.');
        await admin.page.getByLabel('Link (optional)').fill(new URL('/cart', admin.page.url()).href);
        await admin.page.getByRole('button', { name: 'Send notification' }).click();

        await expect(admin.page.getByText('Notification queued for 1 recipient(s).', { exact: true })).toBeVisible();
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

        const productAd = page.getByRole('button', { name: /Product ad/ });
        await productAd.click();

        await expect(productAd).toHaveAttribute('aria-pressed', 'true');
        await expect(page.getByLabel('Title')).toHaveValue('Fresh finds just landed');
        await expect(page.getByLabel('Message')).toHaveValue('Discover new arrivals from JMMart sellers. Browse the latest products and find something to love.');
        await expect(page.getByLabel('Link (optional)')).toHaveValue(new URL('/search', page.url()).href);

        await page.getByLabel('Title').fill('Custom title');
        await expect(productAd).toHaveAttribute('aria-pressed', 'false');
    } finally {
        await context.close();
    }
});

test('admin deep-links a product ad to a store', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'admin');

    try {
        await page.goto('/admin/announcements');
        await rejectOptionalCookies(page);

        await page.getByRole('button', { name: /Product ad/ }).click();
        await page.getByLabel('Open a store directly').selectOption({ label: 'QA Northwind Store' });

        await expect(page.getByLabel('Link (optional)')).toHaveValue(new URL('/stores/qa-northwind-store', page.url()).href);

        const summary = page.locator('aside dl');
        const storeRow = summary.locator('div', { hasText: 'Store deep link' });
        await expect(storeRow).toContainText('QA Northwind Store');

        const productRow = summary.locator('div', { hasText: 'Product deep link' });
        await page.getByLabel('Open a product directly').selectOption({ label: 'QA Canvas Tote' });
        await expect(page.getByLabel('Link (optional)')).toHaveValue(new URL('/stores/qa-northwind-store/products/qa-canvas-tote', page.url()).href);
        await expect(productRow).toContainText('QA Canvas Tote');

        await page.getByLabel('Open a product directly').selectOption('');
        await expect(page.getByLabel('Link (optional)')).toHaveValue(new URL('/stores/qa-northwind-store', page.url()).href);
        await expect(productRow).toContainText('None');

        await page.getByLabel('Open a store directly').selectOption('');
        await expect(page.getByLabel('Link (optional)')).toHaveValue('');
        await expect(storeRow).toContainText('None');
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
        const summaryImage = page.locator('aside dl div', { hasText: 'Campaign image' });

        await page.getByLabel('Open a store directly').selectOption({ label: 'QA Southwind Store' });
        await page.getByLabel('Open a product directly').selectOption({ label: 'QA Variant Speaker' });

        await expect(imageUrl).toHaveValue(/qa\/variant-/);
        await expect(page.getByText('Using product photo')).toBeVisible();
        await expect(page.getByLabel('Title')).toHaveValue(/^QA Variant Speaker · ₱10\.00$/);
        await expect(summaryImage).toContainText('Product photo (auto)');

        await imageUrl.fill('https://example.com/sale-banner.jpg');
        await expect(summaryImage).toContainText('Custom image');

        await page.getByLabel('Open a product directly').selectOption({ label: 'Other Seller QA Product' });
        await expect(imageUrl).toHaveValue('https://example.com/sale-banner.jpg');

        await page.getByLabel('Open a product directly').selectOption({ label: 'QA Variant Speaker' });
        await page.getByRole('button', { name: 'Use product photo' }).click();
        await expect(imageUrl).toHaveValue(/qa\/variant-/);

        await page.getByLabel('Open a store directly').selectOption('');
        await expect(imageUrl).toHaveValue('');
        await expect(summaryImage).toContainText('None');
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
        await expect(page.locator('aside dl div', { hasText: 'Campaign image' })).toContainText('Custom image');
        await expect(page.getByRole('button', { name: 'Replace', exact: true })).toBeVisible();

        await page.getByRole('button', { name: 'Remove', exact: true }).click();
        await expect(page.getByAltText('Campaign image preview')).toHaveCount(0);
        await expect(page.locator('aside dl div', { hasText: 'Campaign image' })).toContainText('None');
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
        await page.getByLabel('Link (optional)').fill('https://example.com/login');
        await page.getByRole('button', { name: 'Send notification' }).click();
        // The layout also lists errors in a summary; the field's own message sits inside the form.
        await expect(page.locator('form').getByText('The link must be a JMMart page.')).toBeVisible();

        await page.getByRole('radio', { name: 'One customer' }).check();
        await page.getByLabel('Customer email').fill('nobody@example.test');
        await page.getByLabel('Link (optional)').fill('');
        await page.getByRole('button', { name: 'Send notification' }).click();
        await expect(page.locator('form').getByText('No active customer has this email.')).toBeVisible();

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
