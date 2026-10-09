import { expect, test } from '@playwright/test';

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
