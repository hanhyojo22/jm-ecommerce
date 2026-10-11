import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { openAsRole } from './support/role-page.js';

test('visitor can accept, reopen, and withdraw functional consent', async ({ page }) => {
    const trackerRequests = [];
    page.on('request', (request) => {
        if (/google-analytics|googletagmanager|facebook\.com\/tr|connect\.facebook\.net|hotjar|segment\.com|mixpanel|clarity\.ms/i.test(request.url())) {
            trackerRequests.push(request.url());
        }
    });

    await page.goto('/');
    await expect(page.getByRole('heading', { name: 'Your privacy matters' })).toBeVisible();
    expect(trackerRequests).toEqual([]);
    await page.getByRole('button', { name: 'Accept All' }).click();
    await expect(page.locator('[data-cookie-banner]')).toHaveCount(0);

    await page.getByRole('button', { name: 'Cookie Preferences', exact: true }).click();
    const dialog = page.getByRole('dialog', { name: 'Cookie preferences' });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('checkbox', { name: /Functional/ })).toBeChecked();
    await page.evaluate(() => localStorage.setItem('seller-dashboard:verification', 'saved'));
    await dialog.getByRole('button', { name: 'Reject Optional' }).click();
    await expect(page.locator('[data-cookie-consent]')).toHaveAttribute('data-functional', 'false');
    expect(await page.evaluate(() => localStorage.getItem('seller-dashboard:verification'))).toBeNull();
    await expect(page.locator('[data-cookie-banner]')).toHaveCount(0);
    await page.getByRole('button', { name: 'Cookie Preferences', exact: true }).click();
    await expect(dialog.getByRole('checkbox', { name: /Functional/ })).not.toBeChecked();
});

test('custom choices persist on mobile without overflow', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 700 });
    await page.goto('/');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    expect((await page.locator('[data-cookie-banner]').boundingBox()).height).toBeLessThan(230);

    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    expect((await page.locator('[data-cookie-banner]').boundingBox()).height).toBeLessThan(190);
    expect((await page.locator('[data-cookie-banner] [data-cookie-action="accept"]').boundingBox()).height).toBeLessThan(40);
    expect(await page.locator('[data-cookie-banner] [data-cookie-action="accept"]').evaluate((button) => {
        const bounds = button.getBoundingClientRect();
        return document.elementFromPoint(bounds.x + bounds.width / 2, bounds.y - 3) === button;
    })).toBe(true);
    await page.getByRole('button', { name: 'Customize' }).click();
    const dialog = page.getByRole('dialog', { name: 'Cookie preferences' });
    await expect(dialog).toBeVisible();
    expect((await new AxeBuilder({ page }).include('[data-cookie-dialog]').analyze()).violations).toEqual([]);
    await dialog.getByRole('checkbox', { name: /Functional/ }).check();
    await dialog.getByRole('button', { name: 'Save Preferences' }).click();
    await expect(page.locator('[data-cookie-banner]')).toHaveCount(0);
    await page.reload();
    await expect(page.locator('[data-cookie-consent]')).toHaveAttribute('data-functional', 'true');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
});

test('rejecting optional storage keeps seller and customer pages available', async ({ browser }) => {
    const seller = await openAsRole(browser, 'seller');
    const customer = await openAsRole(browser, 'customer');

    try {
        await seller.page.goto('/seller');
        await seller.page.getByRole('button', { name: 'Reject Optional' }).click();
        await expect(seller.page).toHaveURL(/\/seller$/);
        await expect(seller.page.getByRole('button', { name: 'Cookie Preferences', exact: true })).toBeVisible();

        await customer.page.goto('/cart');
        await customer.page.getByRole('button', { name: 'Reject Optional' }).click();
        await expect(customer.page).toHaveURL(/\/cart$/);
    } finally {
        await seller.context.close();
        await customer.context.close();
    }
});
