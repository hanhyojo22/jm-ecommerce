import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    server: {
        host: '127.0.0.1',
        watch: {
            /*
             * The application and its tooling write into these three folders on their own: logs
             * and compiled views, built assets, and browser-test output. Vite reloads every open
             * page for each write, so a tab could never finish loading its bundle while a test
             * run or a chatty log was in progress — the page looked half-built and Clerk never
             * mounted. Source folders are still watched.
             */
            ignored: ['**/artifacts/**', '**/storage/**', '**/public/build/**'],
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/product-detail.css', 'resources/css/seller-center.css', 'resources/css/onboarding.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
