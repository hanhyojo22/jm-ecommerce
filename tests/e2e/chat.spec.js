import { expect, test } from '@playwright/test';
import { openAsRole } from './support/role-page.js';

test('seller conversation fills the account layout at desktop and mobile widths', async ({ browser }, testInfo) => {
    const buyer = await openAsRole(browser, 'customer');
    const seller = await openAsRole(browser, 'seller', { viewport: { width: 1440, height: 900 } });

    try {
        await buyer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await buyer.page.getByRole('button', { name: 'Message seller' }).click();
        await seller.page.goto('/messages');
        const inboxBottom = await seller.page.locator('[data-conversation-inbox] .account-messages-shell .account-shell-content > .grid').evaluate((panel) => panel.getBoundingClientRect().bottom);
        expect(900 - inboxBottom).toBeLessThanOrEqual(40);
        await expect(seller.page.getByRole('heading', { name: 'Messages', level: 1 })).toBeVisible();
        await expect(seller.page.getByRole('navigation', { name: 'Seller workspace navigation' })).toContainText('Messages');
        await seller.page.locator('[data-conversation-list] a').first().click();
        await expect(seller.page.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
        const dimensions = await seller.page.evaluate(() => ({
            composerBottom: document.querySelector('[data-conversation-thread] .grid').getBoundingClientRect().bottom,
            viewportHeight: window.innerHeight,
            documentWidth: document.documentElement.scrollWidth,
            viewportWidth: document.documentElement.clientWidth,
        }));
        expect(dimensions.viewportHeight - dimensions.composerBottom).toBeLessThanOrEqual(40);
        expect(dimensions.documentWidth).toBeLessThanOrEqual(dimensions.viewportWidth);
        await seller.page.setViewportSize({ width: 390, height: 844 });
        await expect(seller.page.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
        expect(await seller.page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
        await seller.page.getByRole('button', { name: 'Reject Optional' }).click();
        await expect(seller.page.locator('[data-cookie-banner]')).toBeHidden();
        const mobileComposer = await seller.page.getByRole('textbox', { name: 'Write a message' }).boundingBox();
        expect(mobileComposer.y + mobileComposer.height).toBeLessThanOrEqual(844);
        await seller.page.screenshot({ path: testInfo.outputPath('seller-messages-mobile.png') });
        await seller.page.getByRole('link', { name: 'Back to messages' }).click();
        await expect(seller.page).toHaveURL(/\/messages(?:\?context=selling)?$/);
        await expect(seller.page.locator('[data-conversation-inbox]')).toBeVisible();
        await expect(seller.page.getByRole('heading', { name: 'Messages', level: 1 })).toBeVisible();
        expect(await seller.page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
        await seller.page.screenshot({ path: testInfo.outputPath('seller-inbox-mobile.png') });
    } finally {
        await buyer.context.close();
        await seller.context.close();
    }
});

test('buyer sees an optimistic message that reconciles after confirmation', async ({ browser }) => {
    const buyer = await openAsRole(browser, 'customer');

    try {
        await buyer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await buyer.page.getByRole('button', { name: 'Message seller' }).click();
        await expect(buyer.page.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
        await buyer.page.getByRole('textbox', { name: 'Write a message' }).fill('Optimistic buyer message');
        await buyer.page.route(/\/livewire-[^/]+\/update$/, async (route) => {
            if (route.request().postData()?.includes('"method":"send"')) {
                await new Promise((resolve) => setTimeout(resolve, 700));
            }
            await route.continue();
        });

        await buyer.page.getByRole('button', { name: 'Send message' }).click();
        await expect(buyer.page.locator('[data-pending-message-id]')).toContainText('Sending');
        await expect(buyer.page.locator('article[data-client-message-id]').filter({ hasText: 'Optimistic buyer message' })).toBeVisible();
        await expect(buyer.page.locator('[data-pending-message-id]')).toHaveCount(0);
    } finally {
        await buyer.context.close();
    }
});

test('failed optimistic message can be retried without creating a duplicate', async ({ browser }) => {
    const buyer = await openAsRole(browser, 'customer');

    try {
        await buyer.page.goto('/stores/qa-northwind-store/products/qa-canvas-tote');
        await buyer.page.getByRole('button', { name: 'Message seller' }).click();
        await buyer.page.getByRole('textbox', { name: 'Write a message' }).fill('Retry this message');
        let dropped = false;
        await buyer.page.route(/\/livewire-[^/]+\/update$/, (route) => {
            if (!dropped && route.request().postData()?.includes('"method":"send"')) {
                dropped = true;
                return route.abort('failed');
            }
            return route.continue();
        });

        await buyer.page.getByRole('button', { name: 'Send message' }).click();
        const pending = buyer.page.locator('[data-pending-message-id]').filter({ hasText: 'Retry this message' });
        await expect(pending).toContainText('Failed', { timeout: 15_000 });
        await pending.getByRole('button', { name: 'Retry' }).click();
        await expect(buyer.page.locator('article[data-client-message-id]').filter({ hasText: 'Retry this message' })).toHaveCount(1);
        await expect(pending).toHaveCount(0);
    } finally {
        await buyer.context.close();
    }
});

test('new conversation and replies arrive in two open accounts without a refresh', async ({ browser }) => {
    test.setTimeout(90_000);
    const buyer = await openAsRole(browser, 'customer');
    const seller = await openAsRole(browser, 'seller');

    try {
        await seller.page.goto('/messages', { waitUntil: 'domcontentloaded' });
        await expect.poll(() => seller.page.evaluate(() => window.Echo?.connector.pusher.connection.state)).toBe('connected');
        const newConversation = seller.page.locator('[data-conversation-list] a').filter({ hasText: 'QA Chat Sample' });
        await expect(newConversation).toHaveCount(0);

        await buyer.page.goto('/stores/qa-northwind-store/products/qa-chat-sample');
        await buyer.page.getByRole('button', { name: 'Message seller' }).click();
        await expect(newConversation).toBeVisible();

        await buyer.page.getByRole('textbox', { name: 'Write a message' }).fill('Hello from the buyer');
        await buyer.page.getByRole('button', { name: 'Send message' }).click();
        await expect(buyer.page.locator('article[data-client-message-id]').filter({ hasText: 'Hello from the buyer' })).toBeVisible();
        await expect(newConversation).toContainText('Hello from the buyer');
        const conversationId = new URL(buyer.page.url()).pathname.split('/').pop();
        await expect.poll(() => buyer.page.evaluate((id) => window.Echo?.private(`conversation.${id}`).subscription.subscribed, conversationId)).toBe(true);

        await newConversation.click();
        await expect(seller.page.locator('#conversation-messages').getByText('Hello from the buyer')).toBeVisible();
        await expect.poll(() => seller.page.evaluate((id) => window.Echo?.private(`conversation.${id}`).subscription.subscribed, conversationId)).toBe(true);
        await seller.page.getByRole('textbox', { name: 'Write a message' }).fill('Hello from the seller');
        await seller.page.getByRole('button', { name: 'Send message' }).click();
        await expect(buyer.page.locator('#conversation-messages').getByText('Hello from the seller')).toBeVisible();
    } finally {
        await buyer.context.close();
        await seller.context.close();
    }
});

test('older history remains accessible beyond two hundred messages', async ({ browser }) => {
    const buyer = await openAsRole(browser, 'customer');

    try {
        await buyer.page.goto('/stores/qa-southwind-store/products/qa-history-sample');
        await buyer.page.getByRole('button', { name: 'Message seller' }).click();
        const history = buyer.page.locator('#conversation-messages');
        await expect(history.getByText('History message 225')).toBeVisible();
        await expect(history.getByText('History message 1', { exact: true })).toHaveCount(0);
        await expect.poll(() => buyer.page.evaluate(() => Boolean(document.querySelector('[x-data^="chatThread"]')?._x_dataStack))).toBe(true);
        const beforePrepend = await history.evaluate((list) => {
            list.scrollTop = 0;
            const before = { top: list.scrollTop, height: list.scrollHeight };
            list.querySelector('button').click();
            return before;
        });
        await expect(history.getByText('History message 126', { exact: true })).toBeVisible();
        await expect.poll(() => history.evaluate((list, before) => Math.abs((list.scrollTop - before.top) - (list.scrollHeight - before.height)), beforePrepend)).toBeLessThan(12);
        for (const oldestMessage of [76, 26, 1]) {
            await history.getByRole('button', { name: 'Load earlier messages' }).click();
            await expect(history.getByText(`History message ${oldestMessage}`, { exact: true })).toBeVisible();
        }
        await expect(history.getByText('History message 1', { exact: true })).toBeVisible();
        await history.getByRole('button', { name: 'Jump to latest' }).click();
        await expect(history.getByText('History message 225')).toBeVisible();
    } finally {
        await buyer.context.close();
    }
});

test('reconnect recovers a message sent while the buyer socket was disconnected', async ({ browser }) => {
    test.setTimeout(90_000);
    const buyer = await openAsRole(browser, 'customer');
    const seller = await openAsRole(browser, 'seller');

    try {
        await buyer.page.goto('/stores/qa-northwind-store/products/qa-chat-sample');
        await buyer.page.getByRole('button', { name: 'Message seller' }).click();
        await expect(buyer.page.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
        const conversationId = new URL(buyer.page.url()).pathname.split('/').pop();
        await seller.page.goto('/messages', { waitUntil: 'domcontentloaded' });
        await seller.page.locator('[data-conversation-list] a').filter({ hasText: 'QA Chat Sample' }).click();
        await expect(seller.page.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
        await expect.poll(() => buyer.page.evaluate((id) => window.Echo?.private(`conversation.${id}`).subscription.subscribed, conversationId)).toBe(true);
        await expect.poll(() => seller.page.evaluate((id) => window.Echo?.private(`conversation.${id}`).subscription.subscribed, conversationId)).toBe(true);

        await buyer.page.evaluate(() => window.Echo.connector.pusher.disconnect());
        await expect.poll(() => buyer.page.evaluate(() => window.Echo.connector.pusher.connection.state)).toBe('disconnected');
        await seller.page.getByRole('textbox', { name: 'Write a message' }).fill('Message during reconnect');
        await seller.page.getByRole('button', { name: 'Send message' }).click();
        await expect(seller.page.locator('article[data-client-message-id]').filter({ hasText: 'Message during reconnect' })).toBeVisible();
        await buyer.page.evaluate(() => window.Echo.connector.pusher.connect());
        await expect(buyer.page.locator('#conversation-messages').getByText('Message during reconnect')).toBeVisible();
    } finally {
        await buyer.context.close();
        await seller.context.close();
    }
});

test('offline composer explains why sending is paused', async ({ browser }) => {
    const buyer = await openAsRole(browser, 'customer');

    try {
        await buyer.page.goto('/stores/qa-northwind-store/products/qa-chat-sample');
        await buyer.page.getByRole('button', { name: 'Message seller' }).click();
        await expect(buyer.page.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
        await buyer.context.setOffline(true);
        await expect(buyer.page.getByText('Sending is paused until your connection returns.')).toBeVisible();
        await expect(buyer.page.getByRole('button', { name: 'Send message' })).toBeDisabled();
        await buyer.context.setOffline(false);
        await expect(buyer.page.getByRole('button', { name: 'Send message' })).toBeEnabled();
    } finally {
        await buyer.context.close();
    }
});

test('a file dropped on the message composer is the one that sends', async ({ browser }) => {
    const buyer = await openAsRole(browser, 'customer');

    try {
        await buyer.page.goto('/stores/qa-northwind-store/products/qa-chat-sample');
        await buyer.page.getByRole('button', { name: 'Message seller' }).click();
        await expect(buyer.page.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
        await buyer.page.locator('[data-cookie-banner] [data-cookie-action="reject"]').click();
        await expect(buyer.page.locator('[data-cookie-banner]')).toBeHidden();
        /*
         * A drop carries its file in the event, never through the input, so the composer has to
         * move it there itself — that is the whole of what this covers.
         */
        await buyer.page.getByRole('button', { name: 'Add attachment' }).evaluate((button) => {
            const transfer = new DataTransfer();
            transfer.items.add(new File(['%PDF-1.4 1 0 obj<</Type/Catalog>>endobj %%EOF'], 'dropped.pdf', { type: 'application/pdf' }));
            button.closest('.border-t').dispatchEvent(new DragEvent('drop', { dataTransfer: transfer, bubbles: true }));
        });

        await expect(buyer.page.getByRole('status').filter({ hasText: 'dropped.pdf' })).toBeVisible();

        await buyer.page.getByRole('button', { name: 'Send document' }).click();
        const message = buyer.page.locator('#conversation-messages article').filter({ hasText: 'dropped.pdf' }).last();
        await expect(message.getByRole('link', { name: 'Download' })).toBeVisible();
    } finally {
        await buyer.context.close();
    }
});

test('failed attachment upload retries with one confirmed bubble', async ({ browser }) => {
    const buyer = await openAsRole(browser, 'customer');

    try {
        await buyer.page.goto('/stores/qa-northwind-store/products/qa-chat-sample');
        await buyer.page.getByRole('button', { name: 'Message seller' }).click();
        await expect(buyer.page.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
        await buyer.page.locator('[data-cookie-banner] [data-cookie-action="reject"]').click();
        await expect(buyer.page.locator('[data-cookie-banner]')).toBeHidden();
        await buyer.page.getByRole('button', { name: 'Add attachment' }).click();
        await buyer.page.getByLabel('Image or PDF document').setInputFiles({
            name: 'qa-retry.pdf',
            mimeType: 'application/pdf',
            buffer: Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF'),
        });
        let dropped = false;
        await buyer.page.route(/\/messages\/[^/]+\/attachments$/, (route) => {
            if (!dropped) {
                dropped = true;
                return route.abort('failed');
            }
            return route.continue();
        });
        await buyer.page.getByRole('button', { name: 'Send document' }).click();
        const pending = buyer.page.locator('[data-pending-message-id]').filter({ hasText: 'qa-retry.pdf' });
        await expect(pending).toContainText('Failed');
        const retryResponse = buyer.page.waitForResponse((response) => response.url().includes('/attachments') && response.request().method() === 'POST');
        await pending.getByRole('button', { name: 'Retry' }).click();
        expect((await retryResponse).ok()).toBeTruthy();
        await expect(buyer.page.locator('article[data-client-message-id]').filter({ hasText: 'qa-retry.pdf' })).toHaveCount(1);
        await expect(pending).toHaveCount(0);
    } finally {
        await buyer.context.close();
    }
});

test('Livewire navigation cleans up conversation channels', async ({ browser }) => {
    test.setTimeout(90_000);
    const buyer = await openAsRole(browser, 'customer');

    try {
        await buyer.page.goto('/messages');
        const conversationUrl = await buyer.page.locator('[data-conversation-list] a').filter({ hasText: 'QA History Sample' }).getAttribute('href');
        for (let visit = 0; visit < 2; visit++) {
            await buyer.page.evaluate((url) => window.Livewire.navigate(url), conversationUrl);
            await expect(buyer.page.getByRole('textbox', { name: 'Write a message' })).toBeVisible();
            await expect.poll(() => buyer.page.evaluate(() => Object.keys(window.Echo.connector.pusher.channels.channels).filter((name) => name.startsWith('private-conversation.')).length)).toBe(1);
            await buyer.page.evaluate(() => window.Livewire.navigate('/messages'));
            await expect(buyer.page.locator('[data-conversation-list]')).toBeVisible();
            await expect.poll(() => buyer.page.evaluate(() => Object.keys(window.Echo.connector.pusher.channels.channels).filter((name) => name.startsWith('private-conversation.') || name.startsWith('presence-conversation.')).length)).toBe(0);
            await expect.poll(() => buyer.page.evaluate(() => Object.keys(window.Echo.connector.pusher.channels.channels).filter((name) => name.startsWith('private-chat.inbox.')).length)).toBe(1);
        }
    } finally {
        await buyer.context.close();
    }
});
