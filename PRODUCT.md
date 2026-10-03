# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- Primary: customers seeking affordable, quality products in an easy-to-navigate marketplace.
- Sellers create a shop, manage their own product listings, respond to product reviews, and fulfill their own orders.
- Platform administrators review sellers, products, orders, and review moderation reports.

## Product Purpose

JMMart is a local-first, multi-vendor marketplace that helps customers discover and buy quality products at affordable prices from independent sellers. Success means customers can confidently browse, evaluate, and order products without unnecessary friction.

## Positioning

JMMart combines an accessible marketplace browsing experience with independent-seller storefronts and platform-level governance for listings, orders, and reviews.

## Operating Context

- Buyers browse products and stores, manage a cart, use cash-on-delivery checkout, track orders, and review delivered purchases.
- Sellers operate a tenant-scoped workspace for products, orders, earnings, and customer-review responses.
- Administrators use a marketplace governance workspace to review accounts, sellers, listings, orders, audit records, and moderated reviews.

## Capabilities and Constraints

- Laravel modular monolith using Blade, Livewire, Tailwind, Alpine, PostgreSQL, local public storage, and database-backed queues.
- Roles and server-side permission boundaries: buyer, seller, and platform administrator.
- Product prices and order totals use exact decimal database values; product ratings are calculated from published verified-purchase reviews.
- Payments are limited to cash on delivery for the MVP; shipping integrations, subscriptions, and VPS deployment are outside current scope.
- Marketplace copy and UI should prioritize affordability, product quality, clarity, and ease of navigation.

## Brand Commitments

- Product name: JMMart.
- Existing interface uses a calm marketplace palette centered on emerald, warm gold review stars, neutral text, and generous whitespace.
- Review and rating UI must remain trustworthy, accessible, responsive, and restrained rather than promotional or distracting.

## Evidence on Hand

- Marketplace product, cart, checkout, order, seller, and governance flows in `resources/views/` and `app/`.
- Database migrations and feature tests in `database/migrations/` and `tests/Feature/`.
- No external testimonials, certifications, pricing claims, or customer metrics are supplied; future design work must not invent them.

## Product Principles

1. Make quality and affordability easy to evaluate before checkout.
2. Keep browsing and purchasing clear on small and large screens.
3. Enforce marketplace trust through real order records and server-side permissions.
4. Give sellers operational autonomy without exposing another seller’s data.
5. Keep platform governance visible, accountable, and auditable.

## Accessibility & Inclusion

- Support keyboard access, visible focus states, descriptive controls, readable contrast, and responsive layouts without horizontal overflow.
- Use clear labels and recovery-oriented validation for marketplace tasks.
