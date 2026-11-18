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

test('a customer submits prescription and discount ID together and the pharmacy reviews them separately', async ({ browser }, testInfo) => {
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
        await page.getByRole('checkbox', { name: /Senior citizen \/ PWD discount/ }).check();
        await page.locator('#discount_card_type').selectOption('senior');
        await page.locator('#discount_card_id_number').fill('SC-QA-4821');
        await page.locator('#discount_card_name').fill('Rosa QA Patient');
        await page.locator('#discount_document').setInputFiles({ ...prescriptionPhoto, name: 'senior-id.png' });
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
        const row = page.locator('.sc-queue-order').filter({ hasText: 'Prescription to review' }).first();
        await expect(row).toContainText('QA Amoxicillin 500 mg');
        await row.click();
        const detail = page.getByRole('region', { name: 'Selected order details' });
        await expect(detail.getByRole('button', { name: 'Approve prescription' })).toBeVisible();
        await expect(detail.getByLabel('Message to the customer')).toBeHidden();
        await detail.getByRole('button', { name: 'Request clearer photo', exact: true }).click();
        await expect(detail.getByLabel('Message to the customer')).toBeVisible();
        await detail.getByRole('button', { name: 'Cancel', exact: true }).click();
        await page.setViewportSize({ width: 1440, height: 1000 });
        await expect(detail.getByRole('img', { name: 'Prescription page 1', exact: true })).toBeVisible();
        await page.evaluate(() => window.scrollTo({ top: 0, left: 0, behavior: 'instant' }));
        await page.screenshot({ path: testInfo.outputPath('prescription-review-desktop.png'), fullPage: true });
        await detail.getByRole('button', { name: 'Senior citizen ID', exact: true }).click();
        await expect(detail.getByRole('img', { name: /Senior citizen ID submitted/ })).toBeVisible();
        for (const [name, width] of [['desktop', 1440], ['mobile', 390]]) {
            await page.setViewportSize({ width, height: 1000 });
            const overflow = await page.evaluate(() => ({ width: innerWidth, document: document.documentElement.scrollWidth, elements: [...document.querySelectorAll('body *')].filter(el => el.getBoundingClientRect().right > innerWidth + 1).slice(0, 6).map(el => ({class: el.className, tag: el.tagName, right: Math.round(el.getBoundingClientRect().right)})) }));
            expect(overflow.document, JSON.stringify(overflow)).toBeLessThanOrEqual(overflow.width);
            await page.evaluate(() => window.scrollTo({ top: 0, left: 0, behavior: 'instant' }));
            await page.screenshot({ path: testInfo.outputPath(`combined-review-${name}.png`), fullPage: true });
        }
        await detail.getByRole('button', { name: 'Mark ID reviewed' }).click();
        await expect(detail.getByText(/^ID reviewed/)).toBeAttached();
        await detail.getByRole('button', { name: 'Senior citizen ID', exact: true }).click();
        await expect(detail.getByText(/^ID reviewed/)).toBeVisible();
        await expect(detail.getByRole('button', { name: 'Approve prescription' })).toBeVisible();
        await detail.getByRole('button', { name: 'Approve prescription' }).click();
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
        await page.getByRole('textbox', { name: 'PRC license no.', exact: true }).fill('0123456');
        await page.getByLabel('License valid until', { exact: true }).fill('2030-12-31');
        await page.getByRole('button', { name: 'Add employee' }).click();

        await expect(page.getByRole('status')).toContainText(`Added ${email}`);
        const employee = page.getByRole('list', { name: 'Employees' }).getByRole('listitem').filter({ hasText: email });
        await expect(employee).toContainText('QA Northwind Store');
        await expect(employee).toContainText('PRC 0123456');
    } finally {
        await context.close();
    }
});
