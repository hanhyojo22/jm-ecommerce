import { expect, test } from '@playwright/test';
import { openAsRole } from './support/role-page.js';

for (const [role, path] of [['guest', '/'], ['customer', '/orders'], ['seller', '/seller/products'], ['admin', '/admin/products']]) {
    test(`${role} gets safe, dismissible request feedback on mobile`, async ({ browser }) => {
        const session = role === 'guest'
            ? await (async () => { const context = await browser.newContext({ viewport: { width: 390, height: 844 } }); return { context, page: await context.newPage() }; })()
            : await openAsRole(browser, role, { viewport: { width: 390, height: 844 } });
        try {
            await session.page.goto(path);
            await expect(session.page.locator('[data-request-feedback]')).toBeAttached();
            await session.page.route('**/failure-probe', route => route.fulfill({
                status: 503, contentType: 'application/json',
                headers: { 'X-Request-Id': 'support-browser-123' },
                body: JSON.stringify({ message: 'private provider token' }),
            }));
            await session.page.evaluate(() => fetch('/failure-probe').then(response => response.text()));
            const feedback = session.page.locator('[data-request-feedback]');
            await expect(feedback).toBeVisible();
            await expect(feedback).toContainText('Check your orders');
            await expect(feedback).toContainText('support-browser-123');
            await expect(feedback).not.toContainText('private provider token');
            const bounds = await feedback.boundingBox();
            expect(bounds.x).toBeGreaterThanOrEqual(0);
            expect(bounds.x + bounds.width).toBeLessThanOrEqual(390);
            await feedback.getByRole('button', { name: 'Dismiss', exact: true }).click();
            await expect(feedback).toBeHidden();
        } finally {
            await session.context.close();
        }
    });
}
