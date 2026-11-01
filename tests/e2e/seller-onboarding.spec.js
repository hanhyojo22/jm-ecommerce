import { expect, test } from '@playwright/test';
import { resolve } from 'node:path';
import { openAsRole } from './support/role-page.js';

test('customer applies, admin approves and suspends, and rejected applicant sees the reason', async ({ browser }, testInfo) => {
    test.setTimeout(90_000);
    const customer = await openAsRole(browser, testInfo.retry ? 'customer' : 'applicant');
    const admin = await openAsRole(browser, 'admin');
    const rejected = await openAsRole(browser, 'rejected');
    const logo = resolve('public/images/categories/fashion.png');
    const storeName = testInfo.retry ? 'QA Applicant Retry Goods' : 'QA Applicant Goods';
    const storeSlug = testInfo.retry ? 'qa-applicant-retry-goods' : 'qa-applicant-goods';

    try {
        await customer.page.goto('/start-selling');
        await customer.page.getByRole('button', { name: 'Save and continue' }).click();
        await expect(customer.page).toHaveURL(/step=2/);
        await customer.page.getByRole('button', { name: 'Save & exit' }).click();
        await expect(customer.page).toHaveURL(/\/profile/);
        await customer.page.goto('/start-selling?step=2');
        await customer.page.getByLabel('Full legal name').fill('QA Customer');
        await customer.page.getByLabel('Mobile number').fill('+639171234567');
        await customer.page.getByLabel('Date of birth').fill('1990-01-01');
        await customer.page.getByRole('button', { name: 'Save and continue' }).click();
        await expect(customer.page).toHaveURL(/step=3/);

        await customer.page.getByLabel('Store name').fill(storeName);
        await customer.page.getByLabel('Store link').fill(storeSlug);
        await customer.page.getByLabel('Store description').fill('Useful marketplace goods.');
        await customer.page.getByLabel('Primary product category').fill('Fashion');
        await customer.page.getByLabel('Customer support contact').fill('qa-customer@example.test');
        await customer.page.getByLabel('Store logo').setInputFiles(logo);
        await expect(customer.page.getByAltText('Selected image preview').first()).toBeVisible();
        await customer.page.getByRole('button', { name: 'Save and continue' }).click();

        for (const [label, value] of Object.entries({
            Region: 'NCR', Province: 'Metro Manila', 'City or municipality': 'Manila',
            Barangay: 'Ermita', 'Street address': '10 QA Street', 'Postal code': '1000',
            'Order processing time': '2', 'Fulfillment methods': 'Courier', 'Supported delivery areas': 'Philippines',
        })) {
            await customer.page.getByLabel(label).fill(value);
        }
        await customer.page.getByLabel('Use this address for pickup and returns').check();
        await customer.page.getByRole('button', { name: 'Save and continue' }).click();

        await customer.page.getByLabel('ID type').selectOption('passport');
        await customer.page.getByLabel('ID number').fill('P123456789');
        await customer.page.getByLabel('Front of ID').setInputFiles(logo);
        await customer.page.getByLabel('Identity selfie').setInputFiles(logo);
        await customer.page.getByRole('button', { name: 'Save and continue' }).click();

        await customer.page.getByLabel('Bank or wallet provider').fill('QA Bank');
        await customer.page.getByLabel('Account holder name').fill('QA Customer');
        await customer.page.getByLabel('Account or wallet number').fill('123456789012');
        await customer.page.getByRole('button', { name: 'Save and continue' }).click();
        await expect(customer.page.getByText('•••• 9012')).toBeVisible();
        for (const checkbox of await customer.page.locator('input[type="checkbox"][name^="agreements"]').all()) {
            await checkbox.check();
        }
        await customer.page.getByRole('button', { name: 'Submit application' }).click();
        await expect(customer.page.getByRole('heading', { name: /You.re in review, QA/ })).toBeVisible();
        expect((await customer.page.goto('/seller/products')).status()).toBe(403);
        await expect(customer.page.getByRole('heading', { name: 'Access restricted' })).toBeVisible();

        await admin.page.goto(`/admin/seller-applications?search=${encodeURIComponent(storeName)}`);
        await admin.page.getByRole('link', { name: 'Review application' }).click();
        await expect(admin.page.getByText('•••• 9012')).toBeVisible();
        admin.page.once('dialog', (dialog) => dialog.accept());
        await admin.page.getByRole('button', { name: 'Approve application' }).click();
        await expect(admin.page.getByText('Seller application approved')).toBeVisible();
        await customer.page.goto('/seller');
        await expect(customer.page.getByRole('heading', { name: 'Overview', exact: true })).toBeVisible();

        await admin.page.goto(`/admin/seller-applications?search=${encodeURIComponent(storeName)}`);
        await admin.page.getByRole('link', { name: 'Review application' }).click();
        await admin.page.getByLabel('Suspend seller').fill('Marketplace policy violation confirmed.');
        admin.page.once('dialog', (dialog) => dialog.accept());
        await admin.page.getByRole('button', { name: 'Suspend seller' }).click();
        await expect(admin.page.getByRole('status')).toHaveText('Seller suspended.');
        expect((await customer.page.goto('/seller/products')).status()).toBe(403);
        await expect(customer.page.getByRole('heading', { name: 'Access restricted' })).toBeVisible();
        await customer.page.goto('/start-selling');
        await expect(customer.page.getByText('Your store is currently suspended.')).toBeVisible();

        await rejected.page.goto('/start-selling');
        await expect(rejected.page.getByText('Application rejected')).toBeVisible();
        await expect(rejected.page.getByText('Verification documents did not match the applicant.')).toBeVisible();
    } finally {
        await customer.context.close();
        await admin.context.close();
        await rejected.context.close();
    }
});
