const identities = {
    customer: 'qa-customer-identity',
    integrationCustomer: 'qa-integration-identity',
    seller: 'qa-seller-identity',
    otherSeller: 'qa-other-seller-identity',
    admin: 'qa-admin-identity',
    rejected: 'qa-rejected-identity',
    applicant: 'qa-applicant-identity',
    responsiveApplicant: 'qa-responsive-applicant-identity',
};

export async function openAsRole(browser, role, contextOptions = {}) {
    const identity = identities[role];
    const context = await browser.newContext({
        ...contextOptions,
        extraHTTPHeaders: {
            'X-Playwright-Auth-Key': process.env.PLAYWRIGHT_AUTH_KEY,
            'X-Playwright-Identity': identity,
        },
    });

    await context.addInitScript((sessionToken) => {
        window.clerk = {
            session: { getToken: async () => sessionToken },
            user: { id: 'playwright-user' },
            signOut: async () => {},
        };
    }, `playwright-session-${identity}`);

    return { context, page: await context.newPage() };
}
