import { expect, test } from '@playwright/test';
import { openAsRole } from './support/role-page.js';

test('homepage remains usable without page-level horizontal overflow', async ({ page }) => {
    await page.goto('/');

    await expect(page.getByRole('navigation', { name: 'Marketplace sections' })).toBeVisible();
    await expect(page.getByRole('banner').getByRole('link', { name: 'JMMart marketplace home' })).toBeVisible();

    const dimensions = await page.evaluate(() => ({
        viewport: document.documentElement.clientWidth,
        document: document.documentElement.scrollWidth,
    }));

    expect(dimensions.document).toBeLessThanOrEqual(dimensions.viewport);
});

test('customer header stays the same across shopping and account pages', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');

    try {
        const heights = [];

        for (const path of ['/', '/search', '/stores/qa-northwind-store/products/qa-canvas-tote', '/profile', '/orders', '/cart']) {
            await page.goto(path);
            const header = page.locator('header[data-header-design]');

            await expect(header.getByRole('link', { name: 'JMMart marketplace home' })).toBeVisible();
            await expect(header.getByRole('navigation', { name: 'Marketplace sections' })).toBeVisible();
            await expect(header.getByRole('textbox', { name: 'Search products, brands, and stores' })).toBeVisible();
            await expect(page.locator('[data-header-promo]')).toBeVisible();
            heights.push(Math.round((await header.boundingBox()).height));
        }

        expect(new Set(heights).size).toBe(1);
    } finally {
        await context.close();
    }
});
