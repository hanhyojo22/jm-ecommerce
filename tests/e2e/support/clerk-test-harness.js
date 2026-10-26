/**
 * The E2E server boots with `CLERK_PUBLISHABLE_KEY` empty so the rest of the
 * suite can assert the intentionally handled "auth unavailable" state.
 *
 * A successful handoff still needs to exercise the real browser script,
 * including its fetch, cookie, and redirect path. This helper exposes just the
 * missing meta value for one document and leaves everything else intact:
 * `openAsRole()` already supplies a Clerk SDK stub whose token the Laravel
 * Playwright verifier accepts. No production code or real Clerk account is
 * involved.
 *
 * @param {import('@playwright/test').Page} page
 * @param {{ failSessionRequest?: boolean }} [options]
 */
export async function enableClerkTestHarness(page, options = {}) {
    const { failSessionRequest = false } = options;
    const publishableKeyMeta = /<meta name="clerk-publishable-key" content="[^"]*">/;

    await page.route('**/auth/complete*', async (route) => {
        const request = route.request();

        if (request.method() === 'GET' && request.resourceType() === 'document') {
            const response = await route.fetch();
            const html = await response.text();

            if (!publishableKeyMeta.test(html)) {
                throw new Error('The Clerk handoff document did not expose the expected publishable-key meta tag.');
            }

            await route.fulfill({
                response,
                body: html.replace(
                    publishableKeyMeta,
                    '<meta name="clerk-publishable-key" content="pk_test_playwright_bridge">',
                ),
            });

            return;
        }

        if (failSessionRequest && request.method() === 'POST') {
            await route.abort('failed');

            return;
        }

        await route.continue();
    });
}
