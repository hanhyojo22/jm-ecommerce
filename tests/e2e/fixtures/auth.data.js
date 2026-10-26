/**
 * Realistic authentication fixtures for the POM-based suite.
 *
 * The identities match `database/seeders/PlaywrightQaSeeder.php` and
 * `tests/e2e/support/role-page.js`. Keep this file as data only so the same
 * values can later drive API setup or a different browser project.
 */
export const authRoutes = {
    signIn: '/sign-in',
    signUp: '/sign-up',
    handoff: '/auth/complete',
    cart: '/cart',
};

export const authUsers = {
    buyer: {
        role: 'customer',
        identity: 'qa-customer-identity',
        displayName: 'QA Customer',
        email: 'qa-customer@example.test',
        // The seeded session token the Playwright Clerk verifier accepts.
        sessionToken: 'playwright-session-qa-customer-identity',
        expectedLandingPath: '/cart',
    },
    seller: {
        role: 'seller',
        identity: 'qa-seller-identity',
        displayName: 'QA Seller',
        email: 'qa-seller@example.test',
        sessionToken: 'playwright-session-qa-seller-identity',
    },
    admin: {
        role: 'admin',
        identity: 'qa-admin-identity',
        displayName: 'QA Admin',
        email: 'qa-admin@example.test',
        sessionToken: 'playwright-session-qa-admin-identity',
    },
};

export const redirects = {
    cart: '/cart',
    product: '/stores/qa-northwind-store/products/qa-canvas-tote',
    home: '/',
};

export const invalidRedirects = [
    {
        label: 'protocol-relative external URL',
        value: '//evil.example/account',
    },
    {
        label: 'backslash authority',
        value: '/\\evil.example/account',
    },
    {
        label: 'absolute external URL',
        value: 'https://evil.example/account',
    },
];

/**
 * A value that starts with `/` is a legal local path, so it must be treated as
 * inert text rather than being rejected or interpreted by the database. The
 * assertion in the spec checks that the page still renders normally and no SQL
 * error is exposed.
 */
export const injectionRedirect = "/cart' OR '1'='1";

export const authCopy = {
    signInHeading: 'Welcome back',
    signUpHeading: 'Create your account',
    handoffErrorTitle: 'We could not finish signing you in.',
};
