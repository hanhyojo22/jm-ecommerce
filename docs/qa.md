# Automated QA

## Run locally

- `composer test` runs the PHPUnit feature and unit suites using the in-memory SQLite database defined in `phpunit.xml`.
- `npm run test:e2e` builds the frontend, resets `database/playwright.sqlite`, seeds deterministic QA records, starts an isolated Laravel server on port 4173, and runs Playwright in Chromium.
- `npm run test:qa` runs both suites.
- `npm run test:e2e:ui` opens Playwright's interactive runner.

Reports are written under the ignored `artifacts/` directory: Playwright HTML and JUnit reports, per-test screenshots/videos/traces, and PHPUnit JUnit/Clover coverage reports. Open the HTML report with `npx playwright show-report artifacts/playwright-report`.

The browser server uses a fixed test-only key and fixed seeded identities. The auth bridge is enabled only when `APP_ENV=testing` and `PLAYWRIGHT_AUTH_KEY` is set; it accepts only those fixture identity IDs. Never set that key in a non-test environment. Browser tests use a separate SQLite file; no local, staging, or production database credentials are read.

## Current coverage

The existing PHPUnit suite covers public catalog behavior, Clerk sign-in handoff and signed webhook verification, customer cart/checkout/order snapshots and stock restoration, seller product/order/earning isolation, admin moderation/order operations, and Livewire chat access, idempotency, attachments, receipts, broadcasts, and channel authorization. The Playwright suite covers guest browsing, the COD checkout journey, customer Messages navigation, seller inventory visibility/update, admin pages, role denials, mobile/tablet/desktop overflow, automated WCAG A/AA checks, and a generous local document-load budget.

The app currently supports Cash on Delivery only. It has no online payment gateway, payment webhook endpoint, or refund workflow, so those scenarios are not simulated as if they existed. The current webhook security coverage applies to Clerk's signed webhook. The application has no separate JSON API route set; the Clerk webhook is the JSON HTTP boundary. Reverb is not started by the isolated browser server, so real-time transport is covered by backend event/channel tests rather than a live socket browser test.

## CI and deployment gate

`.github/workflows/qa.yml` runs backend PHPUnit/coverage and browser Playwright checks independently, uploads reports even when a suite fails, and fails the `QA Gate / required-check` job if either suite fails. Configure `QA Gate / required-check` as a required status check in GitHub branch protection or rulesets before merging/deploying from the protected branch. There is no deployment workflow in this repository today; this required check is the repository's merge gate, and any future deployment workflow must depend on the same QA result.
