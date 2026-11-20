import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { openAsRole } from './support/role-page.js';

const speakerPath = '/stores/qa-southwind-store/products/qa-variant-speaker';

async function dismissCookies(page) {
    const reject = page.locator('[data-cookie-action="reject"]').first();
    if (await reject.isVisible()) {
        await Promise.all([page.waitForEvent('domcontentloaded'), reject.click()]);
        await expect(page.locator('[data-cookie-banner]')).toHaveCount(0);
    }
}

test('variant gallery, prices, and sold-out states work on phone, tablet, and desktop', async ({ browser }, testInfo) => {
    test.setTimeout(90_000);
    for (const width of [375, 768, 1440]) {
        const { context, page } = await openAsRole(browser, 'integrationCustomer', { viewport: { width, height: 900 } });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        try {
            await page.goto('/');
            await dismissCookies(page);
            await expect(page.getByRole('heading', { name: /Your pharmacy\.\s*Closer to home\./ })).toBeVisible();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
            await page.goto(speakerPath);
            await expect(page.locator('[data-selected-price]:visible')).toHaveText('₱12.35');
            const gallery = page.getByRole('region', { name: 'Product images' });
            await expect(gallery.locator('button:visible img').first()).toHaveAttribute('src', /variant-black\.jpg/);
            await gallery.getByRole('button', { name: 'Show image 2', exact: true }).click();
            await expect(gallery.getByRole('button', { name: 'Zoom image 2 of 4', exact: true }).locator('img')).toHaveAttribute('src', /variant-white\.jpg/);
            await page.getByRole('radio', { name: /^White/ }).check();
            await expect(page.locator('[data-selected-price]:visible')).toHaveText('₱13.50');
            await expect(gallery.locator('button:visible img').first()).toHaveAttribute('src', /variant-white\.jpg/);
            await expect(page.locator('#purchase').getByRole('button', { name: 'Sold out', exact: true })).toBeDisabled();
            await page.getByRole('radio', { name: /^Navy/ }).check();
            await expect(page.locator('[data-selected-price]:visible')).toHaveText('₱10.00');
            await expect(page.locator('[data-selected-stock]')).toContainText('Only 3 left in Navy / Standard');
            const galleryBox = await gallery.boundingBox();
            const informationBox = await page.locator('.pd-info').boundingBox();
            if (width === 375) {
                expect(galleryBox.y + galleryBox.height).toBeLessThan(informationBox.y);
            } else {
                expect(Math.abs(galleryBox.y - informationBox.y)).toBeLessThan(5);
                expect(Math.abs(galleryBox.width - informationBox.width)).toBeLessThan(2);
            }
            // The description now leads the main column, so measure the column rather than the conversations panel.
            const conversations = await page.locator('.pd-below > div').first().boundingBox();
            const details = await page.locator('.pd-below > aside').boundingBox();
            if (width === 1440) {
                expect(Math.abs(conversations.y - details.y)).toBeLessThan(2);
                expect(conversations.width / details.width).toBeCloseTo(1.7, 1);
            } else {
                expect(details.y).toBeGreaterThan(conversations.y);
            }
            await expect(page.locator('#purchase').getByRole('button', { name: 'Add to cart', exact: true })).toBeEnabled();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
            expect((await new AxeBuilder({ page }).include('main').analyze()).violations).toEqual([]);
            await page.screenshot({ path: testInfo.outputPath(`variant-${width}.png`), fullPage: true });
            expect(errors).toEqual([]);
        } finally {
            await context.close();
        }
    }
});

test('prototype Buy now uses the real selected variant and opens checkout', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'integrationCustomer');
    try {
        await page.goto(speakerPath);
        await dismissCookies(page);
        await page.getByRole('radio', { name: /^Navy/ }).check();
        await page.locator('#purchase').getByRole('button', { name: 'Buy now', exact: true }).click();
        await expect(page).toHaveURL(/\/checkout$/);
        await expect(page.locator('form#checkout-form')).toContainText('QA Variant Speaker');
        await expect(page.locator('form#checkout-form')).toContainText('Navy / Standard');
        await page.goto('/cart');
        await page.getByRole('button', { name: 'Remove QA Variant Speaker from your cart', exact: true }).click();
        await expect(page.locator('article').filter({ hasText: 'QA Variant Speaker' })).toHaveCount(0);
    } finally {
        await context.close();
    }
});

test('COD checkout preserves the selected variant and its price', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'integrationCustomer');
    try {
        await page.goto(speakerPath);
        await dismissCookies(page);
        await page.locator('#purchase').getByRole('button', { name: 'Add to cart', exact: true }).click();
        await expect(page.getByRole('link', { name: 'Cart: 1 item' })).toBeVisible();
        await page.goto('/cart');
        await expect(page.locator('main')).toContainText('Black / Standard');
        await page.getByRole('link', { name: /^Check out(?:\s*\(\s*\d+\s*\))?$/ }).click();
        await expect(page.locator('#checkout-form img[src*="variant-black.jpg"]')).toHaveCount(1);
        await page.getByLabel('Recipient name').fill('QA Integration Customer');
        await page.getByLabel('Mobile number').fill('09171234567');
        await page.getByLabel('Unit / house no. and street').fill('25 Market Street');
        await page.getByLabel('Barangay').fill('Poblacion');
        await page.getByLabel('City / municipality').fill('Cebu');
        await page.getByRole('button', { name: 'Continue to payment' }).click();
        await page.locator('#checkout-form').getByRole('button', { name: 'Review order' }).click();
        await page.locator('#checkout-form').getByRole('button', { name: /Place order/ }).click();
        await expect(page).toHaveURL(/\/orders\/[0-9A-HJKMNP-TV-Z]+\/placed$/i);
        await page.getByRole('link', { name: /Track order/ }).click();
        await expect(page.getByRole('heading', { name: 'Order details' })).toBeVisible();
        await expect(page.getByText('Black / Standard', { exact: true })).toBeVisible();
        await expect(page.getByText('Qty 1 · ₱12.35 each', { exact: true })).toBeVisible();
        await expect(page.locator('img[src*="variant-black.jpg"]')).toHaveCount(1);
    } finally {
        await context.close();
    }
});

test('customer comments and store-name replies remain in the same thread', async ({ browser }) => {
    test.setTimeout(60_000);
    const customer = await openAsRole(browser, 'integrationCustomer');
    const seller = await openAsRole(browser, 'otherSeller');
    try {
        await customer.page.goto(speakerPath);
        await dismissCookies(customer.page);
        await customer.page.locator('#discussion-body').fill('Does the speaker include a cable?');
        await customer.page.getByRole('button', { name: 'Post message', exact: true }).click();
        await expect(customer.page.locator('#discussions article').filter({ hasText: 'Does the speaker include a cable?' })).toBeVisible();
        await seller.page.goto(speakerPath);
        await dismissCookies(seller.page);
        const question = seller.page.locator('#discussions article').filter({ hasText: 'Does the speaker include a cable?' });
        await question.locator('summary').click();
        await question.getByLabel('Your reply', { exact: true }).fill('Yes, a charging cable is included.');
        await question.getByRole('button', { name: 'Post reply', exact: true }).click();
        const answer = seller.page.locator('#discussions article').filter({ hasText: 'Yes, a charging cable is included.' });
        await expect(answer).toContainText('QA Southwind Store');
        await expect(answer).not.toContainText('QA Other Seller');
        await customer.page.reload();
        const reply = customer.page.locator('#discussions article').filter({ hasText: 'Yes, a charging cable is included.' });
        await reply.locator('summary').click();
        await reply.getByLabel('Your reply', { exact: true }).fill('Thank you, that helps.');
        await reply.getByRole('button', { name: 'Post reply', exact: true }).click();
        await expect(customer.page.locator('#discussions article').filter({ hasText: 'Thank you, that helps.' })).toContainText('Reply to QA Southwind Store');
    } finally {
        await customer.context.close();
        await seller.context.close();
    }
});

test('head office publishes medicines and only admin restores suspended listings', async ({ browser }) => {
    const seller = await openAsRole(browser, 'seller');
    const admin = await openAsRole(browser, 'admin');
    try {
        await seller.page.goto('/seller/products?search=QA%20Approval%20Draft');
        await dismissCookies(seller.page);
        await seller.page.getByRole('button', { name: 'Publish QA Approval Draft', exact: true }).first().click();
        await expect(seller.page.getByText('QA Approval Draft is now on sale.')).toBeVisible();
        await admin.page.goto('/admin/products?search=QA%20Approval%20Draft&status=active');
        await dismissCookies(admin.page);
        await admin.page.getByRole('link', { name: 'View QA Approval Draft', exact: true }).click();
        await admin.page.getByLabel('Decision', { exact: true }).selectOption('inactive');
        await admin.page.getByLabel('Reason', { exact: true }).fill('Temporarily suspended for a listing safety review.');
        await Promise.all([admin.page.waitForEvent('domcontentloaded'), admin.page.getByRole('button', { name: 'Save decision', exact: true }).click()]);
        await expect(admin.page.getByText('Listing moderation decision recorded.')).toBeVisible();
        await seller.page.reload();
        await expect(seller.page.getByRole('button', { name: 'Publish QA Approval Draft', exact: true })).toHaveCount(0);
        await admin.page.getByLabel('Decision', { exact: true }).selectOption('active');
        await admin.page.getByLabel('Reason', { exact: true }).fill('The pharmacy listing review is complete.');
        await Promise.all([admin.page.waitForEvent('domcontentloaded'), admin.page.getByRole('button', { name: 'Save decision', exact: true }).click()]);
        await seller.page.goto('/stores/qa-northwind-store/products/qa-approval-draft');
        await expect(seller.page.getByRole('heading', { name: 'QA Approval Draft', exact: true })).toBeVisible();
    } finally { await seller.context.close(); await admin.context.close(); }
});
