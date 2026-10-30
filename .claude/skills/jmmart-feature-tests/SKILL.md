---
name: jmmart-feature-tests
description: Use when writing, fixing, or running JMMart tests — PHPUnit feature tests in tests/Feature or Playwright browser tests in tests/e2e — or when deciding how to verify a JMMart change.
---

# JMMart Tests

## Overview
PHPUnit feature tests against in-memory SQLite are the main safety net; Playwright covers browser journeys against a seeded SQLite file. Also read `testing-best-practices` for general test design.

## PHPUnit Shape

```php
class SellerOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_cannot_update_another_tenants_order(): void
    {
        [$seller] = $this->seller();
        [, $otherTenant, $otherStore] = $this->seller();
        $other = $this->sellerOrder($otherTenant, $otherStore, 'OTHER-1001');

        $this->actingAs($seller)
            ->patch(route('seller.orders.update', $other), ['status' => 'confirmed'])
            ->assertNotFound();

        $this->assertDatabaseHas('seller_orders', ['id' => $other->id, 'status' => 'pending']);
    }

    /** @return array{0: User, 1: Tenant, 2: Store} */
    private function seller(): array { /* User::factory()->seller() + Tenant/Store/TenantMember::create */ }
}
```

| Convention | Detail |
|-----------|--------|
| Create | `php artisan make:test --phpunit FooTest --no-interaction` |
| Names | `test_snake_case_sentence` methods, no `#[Test]` |
| Data | Factories exist only for some models (`database/factories`); others are built with `Model::create` in **private helpers in the same test file** (`seller()`, `sellerOrder()`). Copy a sibling file's helper; `TestCase` is empty. |
| Auth | `actingAs($user)`; set `email_verified_at` (routes use `clerk.verified`). `User::factory()->seller()` for seller accounts. |
| URLs | `route('name', $model)` always |
| Cross-tenant | `assertNotFound()` + DB unchanged |
| Role denial | `assertForbidden()` |
| Rule failures | `assertSessionHasErrors(['status'])`; success `assertSessionHas('status')` |
| Money | assert strings: `'500.00'` |
| External | Clerk and Gmail are HTTP calls: `Http::fake([...])` — see `CheckoutTest` (Gmail) and `AccountTypeAccessTest` (Clerk) |

## Running

```bash
php artisan test --compact tests/Feature/SellerOrderTest.php   # narrowest first
php artisan test --compact --filter=test_seller_cannot_update
composer test                                                  # full PHPUnit
npm run test:e2e                                               # Playwright (builds, seeds, port 4173)
vendor/bin/pint --dirty --format agent                         # after PHP edits
```

## Playwright
- Specs in `tests/e2e/*.spec.js`; page objects in `tests/e2e/pages/*.page.js` (extend `base.page.js`), data in `fixtures/`, role sessions via `support/role-page.js` `openAsRole(browser, role)`.
- Default serial (shared seeded DB); only read-only suites opt into `test.describe.configure({ mode: 'parallel' })`.
- Seeded identities only; the auth bridge works only with `APP_ENV=testing` + `PLAYWRIGHT_AUTH_KEY`.
- Reverb isn't running in e2e — test realtime via backend event/channel tests.

## Common Mistakes
- Adding a shared helper to `TestCase` or a trait — not the house style; keep helpers local.
- Asserting `403` where the controller re-queries through the tenant (it's `404`).
- Running the whole suite when one file covers the change.
