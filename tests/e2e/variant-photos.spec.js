import { expect, test } from '@playwright/test';
import { resolve } from 'node:path';
import { openAsRole } from './support/role-page.js';

const whitePhoto = resolve('public/images/home-product-collection.jpg');
const blackPhoto = resolve('public/images/categories/electronics.png');
const productName = 'QA Color Shirt';

async function dismissCookies(page) {
    const reject = page.locator('[data-cookie-action="reject"]').first();
    if (await reject.isVisible()) {
        await Promise.all([page.waitForEvent('domcontentloaded'), reject.click()]);
        await expect(page.locator('[data-cookie-banner]')).toHaveCount(0);
    }
}

async function addOption(page, values) {
    const option = page.locator('.ps-option').last();
    for (const [index, value] of values.entries()) {
        await option.locator('.ps-values input:not([type="color"])').nth(index).fill(value);
    }
    await option.getByRole('button', { name: 'Done', exact: true }).click();
}

const photoInputs = page => page.locator('input[type="file"][name^="variants["]');
const variantRow = (page, label) => page.locator('.ps-variant').filter({ has: page.getByLabel(`SKU for ${label}`, { exact: true }) });

test('variant photos are shared by every size of their color, from the seller form to the cart', async ({ browser }) => {
    test.setTimeout(120_000);
    const seller = await openAsRole(browser, 'seller');
    const admin = await openAsRole(browser, 'admin');
    const buyer = await openAsRole(browser, 'integrationCustomer');
    const errors = [];
    for (const { page } of [seller, admin, buyer]) {
        page.on('pageerror', error => errors.push(error.message));
    }

    try {
        await seller.page.goto('/seller/products/create');
        await dismissCookies(seller.page);
        await seller.page.getByLabel('Title', { exact: true }).fill(productName);
        await seller.page.getByLabel('Description', { exact: true }).fill('A cotton shirt in two colors.');
        await seller.page.getByLabel('Price', { exact: true }).fill('20');
        await seller.page.getByLabel('SKU (Stock Keeping Unit)').fill('QA-SHIRT');
        await seller.page.getByLabel('Category', { exact: true }).selectOption({ index: 1 });
        await seller.page.getByRole('button', { name: 'Add options like size or color' }).click();
        await addOption(seller.page, ['White', 'Black']);
        await seller.page.getByRole('button', { name: 'Add another option' }).click();
        await addOption(seller.page, ['S', 'M']);

        // Only the first row of each color takes an upload; the other sizes show that color's photos.
        await expect(photoInputs(seller.page)).toHaveCount(2);
        await expect(variantRow(seller.page, 'White / M').locator('.ps-variant-media.is-shared')).toHaveAttribute('title', 'Uses the White photos');
        await variantRow(seller.page, 'White / S').locator('input[type="file"]').setInputFiles(whitePhoto);
        await variantRow(seller.page, 'Black / S').locator('input[type="file"]').setInputFiles(blackPhoto);
        await expect(variantRow(seller.page, 'White / M').locator('.ps-variant-media img')).toHaveAttribute('src', /^blob:/);
        await expect(seller.page.locator('.ps-checks li.is-done').filter({ hasText: 'At least one photo' })).toHaveCount(1);
        for (const label of ['White / S', 'White / M', 'Black / S', 'Black / M']) {
            await seller.page.getByLabel(`Available for ${label}`, { exact: true }).fill('3');
        }
        await seller.page.locator('.ps-footer').getByRole('button').last().click();
        await expect(seller.page).toHaveURL(/\/seller\/products$/);

        await seller.page.goto(`/seller/products?search=${encodeURIComponent(productName)}`);
        await seller.page.getByRole('link', { name: 'Edit listing', exact: true }).first().click();
        await expect(variantRow(seller.page, 'White / M').locator('.ps-variant-media img')).toHaveAttribute('src', /\/products\//);
        await expect(variantRow(seller.page, 'Black / M').locator('.ps-variant-media img')).toHaveAttribute('src', /\/products\//);
        await expect(seller.page.getByRole('button', { name: 'Manage photos' })).toHaveCount(2);

        // No shared photo, but every color has one, so the listing can be submitted.
        await seller.page.goto(`/seller/products?search=${encodeURIComponent(productName)}`);
        await seller.page.getByRole('button', { name: 'Submit for review', exact: true }).first().click();
        await expect(seller.page.getByText('Listing submitted for administrator review.')).toBeVisible();

        await admin.page.goto(`/admin/products?search=${encodeURIComponent(productName)}&status=pending`);
        await dismissCookies(admin.page);
        await admin.page.getByRole('link', { name: `View ${productName}`, exact: true }).click();
        await admin.page.getByLabel('Decision', { exact: true }).selectOption('active');
        await admin.page.getByLabel('Reason', { exact: true }).fill('Photos cover every color.');
        await admin.page.getByRole('button', { name: 'Save decision', exact: true }).click();
        await expect(admin.page.getByText('Listing moderation decision recorded.')).toBeVisible();

        await buyer.page.goto('/stores/qa-northwind-store');
        await dismissCookies(buyer.page);
        await buyer.page.getByRole('link', { name: productName }).first().click();
        const gallery = buyer.page.getByRole('region', { name: 'Product images' });
        await buyer.page.getByRole('radio', { name: /^Black/ }).check();
        const blackSrc = await gallery.locator('button:visible img').first().getAttribute('src');
        await buyer.page.getByRole('radio', { name: /^White/ }).check();
        await buyer.page.getByRole('radio', { name: 'M', exact: true }).check();
        const whiteSrc = await gallery.locator('button:visible img').first().getAttribute('src');
        expect(whiteSrc).not.toBe(blackSrc);
        await buyer.page.getByRole('radio', { name: 'S', exact: true }).check();
        await expect(gallery.locator('button:visible img').first()).toHaveAttribute('src', whiteSrc);

        await buyer.page.getByRole('radio', { name: 'M', exact: true }).check();
        await buyer.page.locator('#purchase').getByRole('button', { name: 'Add to cart', exact: true }).click();
        await expect(buyer.page.getByRole('link', { name: /Cart: \d+ item/ })).toBeVisible();
        await buyer.page.goto('/cart');
        const line = buyer.page.getByRole('region', { name: 'QA Northwind Store', exact: true });
        await expect(line).toContainText('White / M');
        await expect(line.locator(`img[src="${whiteSrc}"]`)).toHaveCount(1);
        expect(errors).toEqual([]);
    } finally {
        await seller.context.close();
        await admin.context.close();
        await buyer.context.close();
    }
});

async function createColorOnlyProduct(page, name, sku) {
    await page.goto('/seller/products/create');
    await dismissCookies(page);
    await page.getByLabel('Title', { exact: true }).fill(name);
    await page.getByLabel('Price', { exact: true }).fill('20');
    await page.getByLabel('SKU (Stock Keeping Unit)').fill(sku);
    await page.getByRole('button', { name: 'Add options like size or color' }).click();
    await addOption(page, ['Black', 'White']);
    await page.getByLabel('Available for Black', { exact: true }).fill('2');
    await page.getByLabel('SKU for Black', { exact: true }).fill(`${sku}-BLACK`);
    await page.locator('.ps-footer').getByRole('button').last().click();
    await expect(page).toHaveURL(/\/seller\/products$/);
    await page.goto(`/seller/products?search=${encodeURIComponent(name)}`);
    await page.getByRole('link', { name: 'Edit listing', exact: true }).first().click();
}

test('sizes can be added to a saved color-only product, and saved variants keep their SKU and stock', async ({ browser }) => {
    test.setTimeout(90_000);
    const seller = await openAsRole(browser, 'seller');
    const errors = [];
    seller.page.on('pageerror', error => errors.push(error.message));
    try {
        // Pressing Done moves each saved color onto its first new size.
        await createColorOnlyProduct(seller.page, 'QA Adopt Done', 'QA-DONE');
        await seller.page.getByRole('button', { name: 'Add another option' }).click();
        await expect(seller.page.getByText('Each saved variant becomes the first size you add')).toBeVisible();
        await addOption(seller.page, ['M', 'L', 'XL']);
        await expect(seller.page.getByLabel('SKU for Black / M', { exact: true })).toHaveValue('QA-DONE-BLACK');
        await expect(seller.page.getByLabel('Available for Black / M', { exact: true })).toHaveValue('2');
        await seller.page.getByRole('button', { name: 'Remove White / XL', exact: true }).click();
        await seller.page.locator('.ps-footer').getByRole('button').last().click();
        await expect(seller.page).toHaveURL(/\/seller\/products$/);
        await seller.page.goto('/seller/products?search=QA%20Adopt%20Done');
        await seller.page.getByRole('link', { name: 'Edit listing', exact: true }).first().click();
        await expect(seller.page.locator('.ps-variant')).toHaveCount(5);
        await expect(seller.page.getByLabel('SKU for Black / M', { exact: true })).toHaveValue('QA-DONE-BLACK');
        await expect(seller.page.getByRole('button', { name: /^Remove / })).toHaveCount(0);

        // Saving with the new option still open moves them too, instead of leaving a size-less variant behind.
        await createColorOnlyProduct(seller.page, 'QA Adopt Open', 'QA-OPEN');
        await seller.page.getByRole('button', { name: 'Add another option' }).click();
        const sizes = seller.page.locator('.ps-option').last().locator('.ps-values input:not([type="color"])');
        await sizes.nth(0).fill('S');
        await sizes.nth(1).fill('M');
        await seller.page.locator('.ps-footer').getByRole('button').last().click();
        await expect(seller.page).toHaveURL(/\/seller\/products$/);
        await seller.page.goto('/seller/products?search=QA%20Adopt%20Open');
        await seller.page.getByRole('link', { name: 'Edit listing', exact: true }).first().click();
        await expect(seller.page.locator('.ps-variant')).toHaveCount(4);
        await expect(seller.page.getByLabel('SKU for Black / S', { exact: true })).toHaveValue('QA-OPEN-BLACK');
        expect(errors).toEqual([]);
    } finally {
        await seller.context.close();
    }
});

test('a color cannot take more than four photos across its sizes', async ({ browser }) => {
    const seller = await openAsRole(browser, 'seller');
    try {
        await seller.page.goto('/seller/products/create');
        await dismissCookies(seller.page);
        await seller.page.getByLabel('Title', { exact: true }).fill('QA Crowded Shirt');
        await seller.page.getByLabel('Price', { exact: true }).fill('20');
        await seller.page.getByLabel('SKU (Stock Keeping Unit)').fill('QA-CROWDED');
        await seller.page.getByRole('button', { name: 'Add options like size or color' }).click();
        await addOption(seller.page, ['White']);
        await variantRow(seller.page, 'White').locator('input[type="file"]').setInputFiles([whitePhoto, whitePhoto, whitePhoto, whitePhoto, whitePhoto]);

        // The fifth file is dropped before upload, so the save goes through with four.
        await expect(variantRow(seller.page, 'White').locator('.ps-variant-media-count')).toHaveText('4');
        await expect(seller.page.getByText('Each color can have up to 4 photos. 4 photos were added for White.')).toBeVisible();
        await seller.page.getByLabel('Available for White', { exact: true }).fill('1');
        await seller.page.locator('.ps-footer').getByRole('button').last().click();

        await expect(seller.page).toHaveURL(/\/seller\/products$/);
    } finally {
        await seller.context.close();
    }
});
