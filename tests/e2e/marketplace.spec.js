import { expect, test } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { openAsRole } from './support/role-page.js';

test('guest can browse a product and is prompted to sign in before adding it to cart', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByRole('navigation', { name: 'Marketplace sections' })).toBeVisible();

    await page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
    await expect(page.getByRole('heading', { name: 'QA Canvas Tote', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Sign in to add to cart' })).toHaveAttribute('href', /sign-in/);
});

test('customer can place a COD order from the product page through checkout', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');

    try {
        await page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await page.locator('#purchase').getByRole('button', { name: 'Add to cart' }).click();
        await expect(page.getByRole('link', { name: 'Cart: 1 item' })).toBeVisible();
        await page.locator('#purchase').getByRole('button', { name: 'Add to cart' }).click();
        await expect(page.getByRole('link', { name: 'Cart: 2 items' })).toBeVisible();
        await page.getByRole('link', { name: 'Cart: 2 items' }).click();
        await expect(page).toHaveURL(/\/cart$/);
        await page.getByRole('link', { name: 'Continue to checkout' }).click();
        await page.getByLabel('Recipient name').fill('Ava QA Customer');
        await page.getByLabel('Mobile number').fill('+639171234567');
        await page.getByLabel('Street address').fill('10 QA Street');
        await page.getByLabel('City').fill('Manila');
        await page.getByRole('button', { name: 'Continue to delivery' }).click();
        await page.getByRole('button', { name: 'Continue to payment' }).click();
        await expect(page.getByText('Cash on delivery', { exact: true })).toBeVisible();
        await page.getByRole('button', { name: 'Review order' }).click();
        await page.getByRole('button', { name: /Place order/ }).click();

        await expect(page).toHaveURL(/\/orders\/[0-9A-HJKMNP-TV-Z]+$/i);
        await expect(page.getByRole('heading', { name: 'Order details' })).toBeVisible();
        await expect(page.getByText('QA Canvas Tote')).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Order summary' }).locator('..').getByText('COD', { exact: true })).toBeVisible();
    } finally {
        await context.close();
    }
});

test('customer refreshes the Clerk session before adding an item to the cart', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');

    try {
        await page.route('**/cart/products/**', async (route) => {
            const headers = { ...route.request().headers() };
            delete headers['x-playwright-auth-key'];
            delete headers['x-playwright-identity'];
            await route.continue({ headers });
        });
        await page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        const addToCartForm = page.locator('#purchase form');
        await expect(addToCartForm).toHaveAttribute('data-clerk-session-refresh', /auth\/session/);
        await expect(addToCartForm).toHaveAttribute('action', /cart\/products\//);
        const refresh = page.waitForResponse((response) => response.url().endsWith('/auth/session') && response.request().method() === 'POST');
        await page.locator('#purchase').getByRole('button', { name: 'Add to cart' }).click();
        await expect((await refresh).ok()).toBeTruthy();

        await expect(page).toHaveURL(/\/stores\/qa-northwind-store\/products\/qa-canvas-tote$/);
        await expect(page.getByRole('link', { name: 'Cart: 1 item' })).toBeVisible();
    } finally {
        await context.close();
    }
});

test('signed-in customer refreshes the Clerk token before opening messages', async ({ browser }) => {
    const { context, page } = await openAsRole(browser, 'customer');

    try {
        await page.goto('/');
        const refresh = page.waitForResponse((response) => response.url().endsWith('/auth/session') && response.request().method() === 'POST');
        // No opt-in attribute here: navigation renews the session for any link it is about to open.
        await page.getByRole('link', { name: /Messages/ }).first().click();

        await expect((await refresh).ok()).toBeTruthy();
        await expect(page).toHaveURL(/\/messages$/);
        await expect(page.getByRole('heading', { name: 'Messages', exact: true })).toBeVisible();
    } finally {
        await context.close();
    }
});

test('messaging workspace adapts from split desktop panels to a mobile chat view', async ({ browser }, testInfo) => {
    const customer = await openAsRole(browser, 'customer');
    try {
        await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await customer.page.getByRole('button', { name: 'Message seller' }).click();
        await customer.page.setViewportSize({ width: 390, height: 844 });
        await customer.page.screenshot({ path: testInfo.outputPath('chat-buyer-mobile.png') });
        await expect(customer.page.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
        const composer = await customer.page.getByRole('button', { name: 'Send message' }).boundingBox();
        expect(composer.y + composer.height).toBeLessThanOrEqual(844);
    } finally {
        await customer.context.close();
    }

    const seller = await openAsRole(browser, 'seller');
    try {
        for (const [device, width] of [['desktop', 1440], ['tablet', 820], ['mobile', 390]]) {
            await seller.page.setViewportSize({ width, height: 844 });
            await seller.page.goto('/messages');
            await expect(seller.page.locator('[data-conversation-list]')).toBeVisible();
            if (width >= 1024) {
                await expect(seller.page.getByRole('heading', { name: 'Select a conversation' })).toBeVisible();
            } else {
                await expect(seller.page.getByRole('heading', { name: 'Select a conversation' })).toBeHidden();
            }
            await seller.page.getByRole('link', { name: /QA Customer/ }).first().click();
            await expect(seller.page.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
            if (width >= 1024) {
                await expect(seller.page.locator('[data-conversation-list]')).toBeVisible();
                await expect(seller.page.locator('[data-conversation-list] a[aria-current="page"]')).toBeVisible();
            } else {
                await expect(seller.page.locator('[data-conversation-list]')).toBeHidden();
                await expect(seller.page.getByRole('link', { name: 'Back to messages' })).toBeVisible();
            }
            const sizes = await seller.page.evaluate(() => ({ viewport: document.documentElement.clientWidth, page: document.documentElement.scrollWidth }));
            expect(sizes.page).toBeLessThanOrEqual(sizes.viewport);
            await seller.page.screenshot({ path: testInfo.outputPath(`chat-${device}.png`) });
        }
    } finally {
        await seller.context.close();
    }
});

test('conversation search and unread filter update the seller inbox', async ({ browser }) => {
    const customer = await openAsRole(browser, 'customer');
    try {
        await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await customer.page.getByRole('button', { name: 'Message seller' }).click();
        await customer.page.getByRole('textbox', { name: 'Write a message' }).fill('Unread filter check');
        await expect(customer.page.locator('#message-help')).toContainText('19/2000');
        await customer.page.getByRole('button', { name: 'Send message' }).click();
        await expect(customer.page.locator('#conversation-messages').getByText('Unread filter check', { exact: true })).toBeVisible();
    } finally {
        await customer.context.close();
    }

    const seller = await openAsRole(browser, 'seller');
    try {
        await seller.page.goto('/messages');
        await seller.page.getByRole('button', { name: 'Unread' }).click();
        await expect(seller.page.getByRole('link', { name: /QA Customer/ }).first()).toBeVisible();
        await seller.page.getByPlaceholder('Search conversations').fill('no matching customer');
        await expect(seller.page.getByRole('heading', { name: 'No matching conversations' })).toBeVisible();
        await seller.page.getByPlaceholder('Search conversations').fill('QA Customer');
        await expect(seller.page.getByRole('link', { name: /QA Customer/ }).first()).toBeVisible();
    } finally {
        await seller.context.close();
    }
});

test('incoming message event refreshes the inbox preview and unread badge without navigation', async ({ browser }) => {
    const customer = await openAsRole(browser, 'customer');
    const seller = await openAsRole(browser, 'seller');

    try {
        await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await customer.page.getByRole('button', { name: 'Message seller' }).click();
        await seller.page.goto('/messages');
        await seller.page.getByRole('link', { name: /QA Customer/ }).first().click();
        await expect(seller.page.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
        await seller.page.goto('/messages');
        const conversationId = new URL(await seller.page.getByRole('link', { name: /QA Customer/ }).first().getAttribute('href')).pathname.split('/').pop();
        await expect.poll(() => seller.page.evaluate((id) => Boolean(window.Echo?.private(`conversation.${id}`).subscription), conversationId)).toBe(true);
        await seller.page.evaluate(() => { window.__chatPageMarker = true; });

        await customer.page.getByRole('textbox', { name: 'Write a message' }).fill('Live inbox preview');
        await customer.page.getByRole('button', { name: 'Send message' }).click();
        await expect(customer.page.locator('#conversation-messages').getByText('Live inbox preview', { exact: true })).toBeVisible();

        await seller.page.evaluate((id) => window.Echo.private(`conversation.${id}`).subscription.emit('message.created', {}), conversationId);
        await expect(seller.page.getByRole('link', { name: /QA Customer/ }).first()).toContainText('Live inbox preview');
        await expect(seller.page.getByRole('link', { name: /QA Customer/ }).first().getByLabel('1 unread messages')).toBeVisible();
        expect(await seller.page.evaluate(() => window.__chatPageMarker)).toBe(true);

        await seller.page.getByRole('link', { name: /QA Customer/ }).first().click();
        await expect(seller.page.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
        await seller.page.evaluate(() => { window.__chatPageMarker = true; });
        await customer.page.getByRole('textbox', { name: 'Write a message' }).fill('Live thread update');
        await customer.page.getByRole('button', { name: 'Send message' }).click();
        await expect(customer.page.locator('#conversation-messages').getByText('Live thread update', { exact: true })).toBeVisible();
        await seller.page.evaluate((id) => window.Echo.private(`conversation.${id}`).subscription.emit('message.created', {}), conversationId);
        await expect(seller.page.locator('#conversation-messages').getByText('Live thread update', { exact: true })).toBeVisible();
        expect(await seller.page.evaluate(() => window.__chatPageMarker)).toBe(true);
    } finally {
        await customer.context.close();
        await seller.context.close();
    }
});

test('seller quick reply fills the message draft without a server request', async ({ browser }) => {
    const customer = await openAsRole(browser, 'customer');

    try {
        await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await customer.page.getByRole('button', { name: 'Message seller' }).click();
        await expect(customer.page).toHaveURL(/\/messages\/[0-9A-HJKMNP-TV-Z]+$/i);
    } finally {
        await customer.context.close();
    }

    const seller = await openAsRole(browser, 'seller');

    try {
        await seller.page.goto('/messages');
        await seller.page.getByRole('link', { name: /QA Customer/ }).click();
        await expect(seller.page.getByRole('textbox', { name: 'Write a message' })).toBeVisible();

        let updateRequests = 0;
        await seller.page.route(/\/livewire-[^/]+\/update$/, (route) => {
            updateRequests++;
            return route.abort();
        });
        for (const reply of ['Yes, this item is available.', 'Your order is being prepared.', 'We’ll update you once your order ships.', 'Could you provide more details?']) {
            await seller.page.getByRole('button', { name: reply }).click();
            await expect(seller.page.getByRole('textbox', { name: 'Write a message' })).toHaveValue(reply);
            await expect(seller.page.locator('#message-help')).toContainText(`${Array.from(reply).length}/2000`);
        }
        expect(updateRequests).toBe(0);
    } finally {
        await seller.context.close();
    }
});

test('seller can send a drafted message after the Clerk cookie expires', async ({ browser }) => {
    const customer = await openAsRole(browser, 'customer');

    try {
        await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await customer.page.getByRole('button', { name: 'Message seller' }).click();
    } finally {
        await customer.context.close();
    }

    const seller = await openAsRole(browser, 'seller');

    try {
        await seller.page.goto('/messages');
        await seller.page.getByRole('link', { name: /QA Customer/ }).click();
        const firstTyping = seller.page.waitForResponse((response) => /\/livewire-[^/]+\/update$/.test(new URL(response.url()).pathname));
        await seller.page.getByRole('textbox', { name: 'Write a message' }).fill('Your order is being prepared.');
        await firstTyping;
        await seller.context.clearCookies({ name: '__session' });
        await seller.context.setExtraHTTPHeaders({});
        await seller.page.evaluate(() => localStorage.setItem('clerk_session_expires_at', String(Date.now() - 1000)));

        const refresh = seller.page.waitForResponse((response) => response.url().endsWith('/auth/session') && response.request().method() === 'POST');
        await seller.page.getByRole('textbox', { name: 'Write a message' }).press('Enter');

        await expect((await refresh).ok()).toBeTruthy();
        await expect(seller.page).toHaveURL(/\/messages\/[0-9A-HJKMNP-TV-Z]+$/i);
        await expect(seller.page.locator('#conversation-messages').getByText('Your order is being prepared.', { exact: true })).toBeVisible();
        await expect(seller.page.getByRole('textbox', { name: 'Write a message' })).toBeEmpty();

        const secondTyping = seller.page.waitForResponse((response) => /\/livewire-[^/]+\/update$/.test(new URL(response.url()).pathname));
        await seller.page.getByRole('textbox', { name: 'Write a message' }).fill('Please check your order details.');
        await secondTyping;
        await seller.context.clearCookies({ name: '__session' });
        await seller.page.evaluate(() => localStorage.setItem('clerk_session_expires_at', String(Date.now() - 1000)));
        await seller.page.route('**/auth/session', (route) => route.fulfill({ status: 401, body: '{}' }));
        await seller.page.getByRole('button', { name: 'Send message' }).click();

        await expect(seller.page.getByRole('alert')).toContainText('session could not be refreshed');
        await expect(seller.page.getByRole('textbox', { name: 'Write a message' })).toHaveValue('Please check your order details.');
        await expect(seller.page.locator('#conversation-messages').getByText('Please check your order details.', { exact: true })).toHaveCount(0);
        await expect(seller.page).toHaveURL(/\/messages\/[0-9A-HJKMNP-TV-Z]+$/i);
    } finally {
        await seller.context.close();
    }
});

test('chat message time follows the viewer local timezone', async ({ browser }) => {
    const timezoneId = new Date().getUTCHours() < 11 ? 'Etc/GMT+12' : 'Pacific/Kiritimati';
    const customer = await openAsRole(browser, 'customer', { timezoneId });

    try {
        await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await customer.page.getByRole('button', { name: 'Message seller' }).click();
        await expect(customer.page).toHaveURL(/\/messages\/[0-9A-HJKMNP-TV-Z]+$/i);
        await customer.page.reload();
        const typing = customer.page.waitForResponse((response) => /\/livewire-[^/]+\/update$/.test(new URL(response.url()).pathname));
        await customer.page.getByRole('textbox', { name: 'Write a message' }).fill('Local time check');
        await typing;
        await customer.page.getByRole('button', { name: 'Send message' }).click();

        const messageTime = customer.page.locator('#conversation-messages article').filter({ hasText: 'Local time check' }).locator('time');
        await expect(messageTime).toBeVisible();
        const expectedTime = await messageTime.evaluate((element) => new Intl.DateTimeFormat('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        }).format(new Date(element.dateTime)));
        await expect(messageTime).toHaveText(expectedTime);

        const dateSeparator = customer.page.locator('#conversation-messages [data-chat-date-separator]:visible').last();
        const expectedDate = await messageTime.evaluate((element) => {
            const sentAt = new Date(element.dateTime);
            const today = new Date();
            const yesterday = new Date(today);
            yesterday.setDate(today.getDate() - 1);

            if (sentAt.toDateString() === today.toDateString()) return 'Today';
            if (sentAt.toDateString() === yesterday.toDateString()) return 'Yesterday';

            return new Intl.DateTimeFormat('en-US', { month: 'long', day: 'numeric', year: 'numeric' }).format(sentAt);
        });
        await expect(dateSeparator).toBeVisible();
        await expect(dateSeparator.locator('[data-chat-date-label]')).toHaveText(expectedDate);
    } finally {
        await customer.context.close();
    }
});

for (const attachment of [
    { label: 'photo', filename: 'chat-photo.png', mimeType: 'image/png', contents: 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/B9sAAAAASUVORK5CYII=' },
    { label: 'document', filename: 'chat-document.pdf', mimeType: 'application/pdf', contents: Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF') },
]) {
    test(`seller can upload a ${attachment.label} after the Clerk cookie expires`, async ({ browser }) => {
        const customer = await openAsRole(browser, 'customer');

        try {
            await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
            await customer.page.getByRole('button', { name: 'Message seller' }).click();
        } finally {
            await customer.context.close();
        }

        const seller = await openAsRole(browser, 'seller');

        try {
            await seller.page.goto('/messages');
            await seller.page.getByRole('link', { name: /QA Customer/ }).click();
            const rerender = seller.page.waitForResponse((response) => /\/livewire-[^/]+\/update$/.test(new URL(response.url()).pathname));
            await seller.page.getByRole('textbox', { name: 'Write a message' }).fill('Draft before uploading');
            await rerender;

            await seller.page.getByRole('button', { name: 'Add attachment' }).click();
            await seller.page.getByLabel(attachment.label === 'photo' ? 'Image' : 'PDF document').setInputFiles({
                name: attachment.filename,
                mimeType: attachment.mimeType,
                buffer: typeof attachment.contents === 'string' ? Buffer.from(attachment.contents, 'base64') : attachment.contents,
            });
            await expect(seller.page.getByRole('status').filter({ hasText: attachment.filename })).toBeVisible();
            await expect(seller.page.getByRole('button', { name: 'Send message' })).toHaveCount(0);
            if (attachment.label === 'photo') {
                await seller.page.setViewportSize({ width: 390, height: 844 });
                const composerRow = await seller.page.evaluate(() => {
                    const selectors = ['button[aria-label="Add attachment"]', '#message-body', 'button[aria-label="Send photo"]'];
                    return selectors.map((selector) => document.querySelector(selector).getBoundingClientRect().bottom);
                });
                expect(Math.max(...composerRow) - Math.min(...composerRow)).toBeLessThan(3);
                await seller.page.getByRole('button', { name: 'Remove attachment' }).click();
                await expect(seller.page.getByRole('button', { name: 'Send message' })).toBeVisible();
                await seller.page.getByRole('button', { name: 'Add attachment' }).click();
                await seller.page.getByLabel('Image').setInputFiles({
                    name: attachment.filename,
                    mimeType: attachment.mimeType,
                    buffer: Buffer.from(attachment.contents, 'base64'),
                });
            }
            await seller.context.clearCookies({ name: '__session' });
            await seller.context.setExtraHTTPHeaders({});
            await seller.page.evaluate(() => localStorage.setItem('clerk_session_expires_at', String(Date.now() - 1000)));

            const refresh = seller.page.waitForResponse((response) => response.url().endsWith('/auth/session') && response.request().method() === 'POST');
            await seller.page.getByRole('button', { name: `Send ${attachment.label}` }).click();

            await expect((await refresh).ok()).toBeTruthy();
            await expect(seller.page).toHaveURL(/\/messages\/[0-9A-HJKMNP-TV-Z]+$/i);
            const sentMessage = seller.page.locator('#conversation-messages article').filter({ hasText: attachment.filename }).last();
            await expect(sentMessage.getByText(attachment.filename, { exact: true })).toBeVisible();
            await expect(sentMessage.getByText('Draft before uploading', { exact: true })).toBeVisible();
            await expect(seller.page.getByRole('textbox', { name: 'Write a message' })).toBeEmpty();
        } finally {
            await seller.context.close();
        }
    });
}

test('an invalid chat file shows an error and can be replaced and retried', async ({ browser }) => {
    const customer = await openAsRole(browser, 'customer');
    try {
        await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await customer.page.getByRole('button', { name: 'Message seller' }).click();
    } finally {
        await customer.context.close();
    }

    const seller = await openAsRole(browser, 'seller');
    try {
        await seller.page.goto('/messages');
        await seller.page.getByRole('link', { name: /QA Customer/ }).first().click();
        await seller.page.getByRole('textbox', { name: 'Write a message' }).fill('Here is the receipt.');
        await seller.page.getByRole('button', { name: 'Add attachment' }).click();
        await seller.page.getByLabel('PDF document').setInputFiles({ name: 'notes.txt', mimeType: 'text/plain', buffer: Buffer.from('not a PDF') });

        await seller.page.route(/\/messages\/[^/]+\/attachments$/, async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 500));
            await route.continue();
        }, { times: 1 });
        const rejected = seller.page.waitForResponse((response) => response.url().includes('/attachments') && response.request().method() === 'POST');
        await seller.page.getByRole('button', { name: 'Send document' }).click();
        await expect(seller.page.getByRole('progressbar', { name: 'Attachment upload progress' })).toBeVisible();
        expect((await rejected).status()).toBe(422);
        await expect(seller.page.getByRole('alert')).toContainText('attachment');
        await expect(seller.page.getByRole('textbox', { name: 'Write a message' })).toHaveValue('Here is the receipt.');
        await expect(seller.page.getByRole('button', { name: 'Send document' })).toBeEnabled();

        await seller.page.getByRole('button', { name: 'Add attachment' }).click();
        await seller.page.getByLabel('PDF document').setInputFiles({ name: 'receipt.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF') });
        await expect(seller.page.getByRole('status').filter({ hasText: 'receipt.pdf' })).toContainText('B');
        await seller.page.getByRole('button', { name: 'Send document' }).click();
        const message = seller.page.locator('#conversation-messages article').filter({ hasText: 'receipt.pdf' }).last();
        await expect(message.getByText('Here is the receipt.', { exact: true })).toBeVisible();
        await expect(message.getByRole('link', { name: 'Open' })).toBeVisible();
        await expect(message.getByRole('link', { name: 'Download' })).toBeVisible();
    } finally {
        await seller.context.close();
    }
});

test('public product page has no automated WCAG A or AA violations', async ({ page }) => {
    await page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');

    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .analyze();

    expect(results.violations).toEqual([]);
});

test('messaging workspace has no browser exceptions or automated WCAG A or AA violations', async ({ browser }) => {
    const customer = await openAsRole(browser, 'customer');
    const exceptions = [];
    customer.page.on('pageerror', (error) => exceptions.push(error.message));

    try {
        await customer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await customer.page.getByRole('button', { name: 'Message seller' }).click();
        await expect(customer.page.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
        const results = await new AxeBuilder({ page: customer.page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
            .analyze();

        expect(results.violations).toEqual([]);
        expect(exceptions).toEqual([]);
    } finally {
        await customer.context.close();
    }
});

test('marketplace document responds within the local performance budget', async ({ page }) => {
    const response = await page.goto('/', { waitUntil: 'domcontentloaded' });

    expect(response?.ok()).toBe(true);
    const domContentLoaded = await page.evaluate(() => performance.getEntriesByType('navigation')[0]?.domContentLoadedEventEnd ?? Infinity);
    expect(domContentLoaded).toBeLessThan(8_000);
});
