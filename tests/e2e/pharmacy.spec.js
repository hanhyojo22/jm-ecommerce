import { expect, test } from '@playwright/test';
import { openAsRole } from './support/role-page.js';

// A 1x1 PNG is enough for the upload: the pharmacist only needs a file to review.
const prescriptionPhoto = {
    name: 'prescription.png',
    mimeType: 'image/png',
    buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', 'base64'),
};

async function rejectOptionalCookies(page) {
    const banner = page.locator('[data-cookie-banner]');
    if (await banner.count()) {
        await Promise.all([page.waitForEvent('domcontentloaded'), banner.locator('[data-cookie-action="reject"]').click()]);
    }
}

test('a customer buys an Rx medicine with a prescription and the pharmacist approves it', async ({ browser }) => {
    test.setTimeout(90_000);
    const customer = await openAsRole(browser, 'pharmacyCustomer');

    try {
        const { page } = customer;
        await page.goto('/stores/qa-northwind-store/products/qa-amoxicillin');
        await rejectOptionalCookies(page);
        await page.locator('#purchase').getByRole('button', { name: 'Add to cart' }).click();
        await expect(page.locator('[data-cart-link]')).toHaveAttribute('aria-label', /^Cart: 1 item$/);

        await page.locator('[data-cart-link]').click();
        await page.getByRole('link', { name: /^Check out(?:\s*\(\s*\d+\s*\))?$/ }).click();
        await page.getByLabel('Recipient name').fill('Rosa QA Patient');
        await page.getByLabel('Mobile number').fill('+639171234567');
        await page.getByLabel('Street address').fill('12 QA Street');
        await page.getByLabel('City').fill('Manila');
        await page.getByRole('button', { name: 'Continue to payment' }).click();
        await page.locator('#checkout-form').getByRole('button', { name: 'Review order' }).click();

        // An Rx order cannot be placed without the prescription.
        await expect(page.getByText('QA Amoxicillin 500 mg needs a doctor’s prescription.', { exact: false })).toBeVisible();
        await page.locator('#checkout-form').getByRole('button', { name: /Place order/ }).click();
        await expect(page.locator('p[role="alert"]').filter({ hasText: 'Upload a photo of your prescription' })).toBeVisible();

        await page.locator('#checkout-form').getByRole('button', { name: 'Review order' }).click().catch(() => {});
        await page.locator('#prescription').setInputFiles(prescriptionPhoto);
        await page.locator('#checkout-form').getByRole('button', { name: /Place order/ }).click();
        await expect(page).toHaveURL(/\/orders\/[0-9A-HJKMNP-TV-Z]+\/placed$/i);
        await page.getByRole('link', { name: /Track order/ }).click();
        await expect(page.getByRole('heading', { name: /Prescription · Waiting for pharmacist review/ })).toBeVisible();
    } catch (error) {
        await customer.context.close();
        throw error;
    }

    const pharmacist = await openAsRole(browser, 'seller');
    try {
        const { page } = pharmacist;
        await page.goto('/seller/orders');
        await rejectOptionalCookies(page);
        const row = page.locator('tr').filter({ hasText: 'Prescription to review' }).first();
        await expect(row).toContainText('QA Amoxicillin 500 mg');
        await page.goto(await row.getByRole('link').first().getAttribute('href'));
        await page.getByRole('button', { name: 'Approve prescription' }).click();
        await expect(page.getByRole('button', { name: 'Approve prescription' })).toHaveCount(0);
    } finally {
        await pharmacist.context.close();
    }

    try {
        await customer.page.reload();
        await expect(customer.page.getByRole('heading', { name: /Prescription · Approved/ })).toBeVisible();
    } finally {
        await customer.context.close();
    }
});

test('head office adds a pharmacist to a branch from the Staff page', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'seller');
    const email = `pharmacist-${Date.now()}@example.test`;

    try {
        await page.goto('/seller/staff');
        await rejectOptionalCookies(page);
        await page.getByRole('textbox', { name: 'Email' }).fill(email);
        await page.getByRole('combobox', { name: 'Role', exact: true }).selectOption('staff');
        await page.getByRole('combobox', { name: 'Works at' }).selectOption({ label: 'QA Northwind Store' });
        await page.getByRole('checkbox', { name: 'Pharmacist' }).first().check();
        await page.getByRole('button', { name: 'Add employee' }).click();

        await expect(page.getByRole('status')).toContainText(`Added ${email}`);
        const employee = page.getByRole('list', { name: 'Employees' }).getByRole('listitem').filter({ hasText: email });
        await expect(employee).toContainText('QA Northwind Store');
        await expect(employee).toContainText('Pharmacist');
    } finally {
        await context.close();
    }
});
