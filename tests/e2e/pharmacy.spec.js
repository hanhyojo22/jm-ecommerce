import { expect, test } from '@playwright/test';
import { openAsRole } from './support/role-page.js';

// A 1x1 PNG is enough for the upload: the pharmacist only needs a file to review.
async function documentPhoto(browser) {
    const context = await browser.newContext({ viewport: { width: 640, height: 760 } });
    try {
        const page = await context.newPage();
        await page.setContent('<main style="padding:48px;font:18px/1.8 sans-serif;color:#182c48"><p>Everyday Pharmacy browser test</p><h1>Sample document</h1><hr><p>For viewer verification only.</p><p>Customer: QA Patient</p><p>Reference: QA-TEST-001</p><p>This fixture contains no real medical or identity information.</p></main>');
        return { name: 'prescription.png', mimeType: 'image/png', buffer: await page.screenshot() };
    } finally {
        await context.close();
    }
}

async function rejectOptionalCookies(page) {
    const banner = page.locator('[data-cookie-banner]');
    if (await banner.count()) {
        await Promise.all([page.waitForEvent('domcontentloaded'), banner.locator('[data-cookie-action="reject"]').click()]);
    }
}

test('a customer submits prescription and discount ID together and the pharmacy reviews them separately', async ({ browser }, testInfo) => {
    test.setTimeout(90_000);
    const prescriptionPhoto = await documentPhoto(browser);
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
        await page.getByLabel('Unit / house no. and street').fill('12 QA Street');
        await page.getByLabel('Barangay').fill('Poblacion');
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
        await expect(detail.getByRole('button', { name: 'Approve prescription and accept order' })).toBeVisible();
        await expect(detail.getByLabel('Message to the customer')).toBeHidden();
        await detail.getByRole('button', { name: 'Request clearer photo', exact: true }).click();
        await expect(detail.getByLabel('Message to the customer')).toBeVisible();
        await detail.getByRole('button', { name: 'Cancel', exact: true }).click();
        await page.setViewportSize({ width: 1440, height: 1000 });
        await expect(detail.getByRole('img', { name: 'Prescription page 1', exact: true })).toBeVisible();
        await page.evaluate(() => window.scrollTo({ top: 0, left: 0, behavior: 'instant' }));
        await page.screenshot({ path: testInfo.outputPath('prescription-review-desktop.png'), fullPage: true });
        await expect(detail.locator('.sc-id-thumb img')).toBeVisible();
        for (const [name, width] of [['desktop', 1440], ['mobile', 390]]) {
            await page.setViewportSize({ width, height: 1000 });
            const overflow = await page.evaluate(() => ({ width: innerWidth, document: document.documentElement.scrollWidth, elements: [...document.querySelectorAll('body *')].filter(el => el.getBoundingClientRect().right > innerWidth + 1).slice(0, 6).map(el => ({class: el.className, tag: el.tagName, right: Math.round(el.getBoundingClientRect().right)})) }));
            expect(overflow.document, JSON.stringify(overflow)).toBeLessThanOrEqual(overflow.width);
            await page.evaluate(() => window.scrollTo({ top: 0, left: 0, behavior: 'instant' }));
            await page.screenshot({ path: testInfo.outputPath(`combined-review-${name}.png`), fullPage: true });
        }
        await detail.getByRole('button', { name: 'Open Senior citizen ID in a full-screen preview' }).click();
        const idLightbox = page.getByRole('dialog', { name: 'Senior citizen ID preview' });
        await expect(idLightbox.getByRole('img', { name: /Senior citizen ID submitted/ })).toBeVisible();
        await idLightbox.getByRole('button', { name: 'Close ID preview' }).click();
        await detail.getByRole('button', { name: 'Approve ID' }).click();
        await expect(detail.getByText(/^ID reviewed/)).toBeAttached();
        await expect(detail.getByRole('button', { name: 'Approve prescription and accept order' })).toBeVisible();
        await detail.getByRole('button', { name: 'Approve prescription and accept order' }).click();
        await expect(page.getByRole('button', { name: 'Approve prescription and accept order' })).toHaveCount(0);
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
        await page.getByRole('button', { name: 'Invite member', exact: true }).click();
        const invitation = page.getByRole('dialog', { name: 'Invite member', exact: true });
        await expect(invitation).toBeVisible();
        await invitation.getByRole('textbox', { name: 'Email', exact: true }).fill(email);
        await invitation.getByRole('combobox', { name: 'Role', exact: true }).selectOption('staff');
        await invitation.getByRole('checkbox', { name: 'Pharmacist', exact: true }).check();
        await invitation.getByRole('textbox', { name: 'PRC license no.', exact: true }).fill('0123456');
        await invitation.getByLabel('License valid until', { exact: true }).fill('2030-12-31');
        await invitation.getByRole('button', { name: 'Add employee' }).click();

        await expect(invitation).toBeVisible();
        await expect(invitation).toContainText('Staff work at a branch.');
        await expect(invitation.getByRole('textbox', { name: 'Email', exact: true })).toHaveValue(email);
        await invitation.getByRole('combobox', { name: 'Works at' }).selectOption({ label: 'QA Northwind Store' });
        await invitation.getByRole('button', { name: 'Add employee' }).click();

        await expect(page.getByRole('status')).toContainText(`Added ${email}`);
        const employee = page.getByRole('table', { name: 'Employees' }).getByRole('row').filter({ hasText: email });
        await expect(employee).toContainText('QA Northwind Store');
        await expect(employee).toContainText('PRC 0123456');
        await employee.getByRole('button', { name: /^Edit / }).click();
        const editor = page.getByRole('dialog', { name: 'Edit member', exact: true });
        await editor.getByRole('textbox', { name: 'PRC license no.', exact: true }).fill('invalid');
        // Wait for the rejected save to reload the page, or the next steps act on the old, submitting form.
        const rejectedSave = page.waitForEvent('load');
        await editor.getByRole('button', { name: 'Save changes' }).click();
        await rejectedSave;
        await expect(editor).toBeVisible();
        await expect(editor.getByRole('textbox', { name: 'PRC license no.', exact: true })).toHaveValue('invalid');
        await editor.getByRole('textbox', { name: 'PRC license no.', exact: true }).fill('0765432');
        await editor.getByRole('button', { name: 'Save changes' }).click();
        await expect(page.getByRole('status')).toContainText('Employee updated.');
        await expect(employee).toContainText('PRC 0765432');
    } finally {
        await context.close();
    }
});
