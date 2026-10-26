import { BasePage } from './base.page.js';

/**
 * The buyer's cart, used here as the landing assertion after a successful
 * authentication handoff.
 */
export class CartPage extends BasePage {
    static path = '/cart';

    /** @param {import('@playwright/test').Page} page */
    constructor(page) {
        super(page);

        // The visible page title is a level-one heading; scoping by role avoids
        // coupling the test to the `customer-page-title` utility class.
        this.heading = page.getByRole('heading', { level: 1, name: 'Shopping cart' });
        this.accountMenu = page.getByRole('button', { name: 'Account menu' });
    }

    async goto() {
        return super.goto(CartPage.path);
    }
}
