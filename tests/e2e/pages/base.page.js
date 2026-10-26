/**
 * Shared behavior for page objects.
 *
 * Page objects own locators and user actions; assertions stay in the spec so a
 * failure points at the scenario rather than at an abstraction.
 */
export class BasePage {
    /** @param {import('@playwright/test').Page} page */
    constructor(page) {
        this.page = page;
    }

    /**
     * Navigate with the suite's standard readiness signal.
     *
     * `domcontentloaded` is deliberate: the app is server-rendered, and waiting
     * for `networkidle` would make every assertion vulnerable to background
     * Livewire or Reverb traffic.
     *
     * @param {string} path
     * @param {import('@playwright/test').PageGotoOptions} [options]
     */
    async goto(path, options = {}) {
        return this.page.goto(path, { waitUntil: 'domcontentloaded', ...options });
    }
}
