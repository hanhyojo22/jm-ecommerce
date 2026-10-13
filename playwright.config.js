import { defineConfig, devices } from '@playwright/test';
import { resolve } from 'node:path';

const appKey = 'base64:MTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0NTY3ODkwMTI=';
const playwrightAuthKey = process.env.PLAYWRIGHT_AUTH_KEY || 'local-playwright-key-is-test-only';
const e2eEnvironment = {
    APP_ENV: 'testing',
    APP_DEBUG: 'false',
    APP_KEY: appKey,
    APP_URL: 'http://127.0.0.1:4173',
    DB_CONNECTION: 'sqlite',
    DB_URL: '',
    DB_DATABASE: resolve('database/playwright.sqlite'),
    SESSION_DRIVER: 'database',
    SESSION_COOKIE: 'jmmart_playwright_session',
    CACHE_STORE: 'array',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array',
    BROADCAST_CONNECTION: 'reverb',
    REVERB_APP_ID: 'local-playwright-app',
    REVERB_APP_KEY: 'local-playwright-key-is-test-only',
    REVERB_APP_SECRET: 'local-playwright-secret-is-test-only',
    REVERB_HOST: '127.0.0.1',
    REVERB_PORT: '4180',
    REVERB_SERVER_HOST: '127.0.0.1',
    REVERB_SERVER_PORT: '4180',
    REVERB_SCHEME: 'http',
    REVERB_ALLOWED_ORIGINS: '127.0.0.1',
    CLERK_PUBLISHABLE_KEY: '',
    CLERK_SECRET_KEY: '',
    PLAYWRIGHT_AUTH_KEY: playwrightAuthKey,
};

Object.assign(process.env, e2eEnvironment);

const reporters = [
    ...(process.env.CI ? [['github', { omitTags: true }]] : [['list']]),
    ['html', { outputFolder: 'artifacts/playwright-report', open: 'never' }],
    ['junit', { outputFile: 'artifacts/playwright-junit.xml' }],
];

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: reporters,
    outputDir: 'artifacts/playwright-results',
    expect: { timeout: 10_000 },
    timeout: 30_000,
    use: {
        baseURL: 'http://127.0.0.1:4173',
        actionTimeout: 10_000,
        navigationTimeout: 20_000,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        { name: 'desktop-chromium', use: { ...devices['Desktop Chrome'] } },
        {
            name: 'tablet-chromium',
            testMatch: '**/responsive.spec.js',
            use: { ...devices['iPad Mini'], browserName: 'chromium' },
        },
        {
            name: 'mobile-chromium',
            testMatch: '**/responsive.spec.js',
            use: { ...devices['Pixel 7'] },
        },
    ],
    webServer: {
        command: 'npm run qa:e2e:server',
        url: 'http://127.0.0.1:4173',
        reuseExistingServer: false,
        timeout: 180_000,
        env: e2eEnvironment,
    },
});
