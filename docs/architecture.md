# JMMart architecture

JMMart is a Laravel modular monolith. Blade and Livewire provide the web UI; PostgreSQL is the source of truth; Redis is reserved for queues, cache, and rate limiting once the local Redis service is added. The first release uses local public storage for product images and Laravel's log mailer for local verification mail.

## Modules

- **Marketplace**: public catalogue, search, store pages, cart, checkout, and customer orders.
- **Seller**: application onboarding, tenant membership, store settings, product management, inventory, and seller-order fulfilment.
- **Platform**: administration of applications, tenants, stores, categories, products, customers, orders, and audit logs.
- **Shared**: authentication, authorization, money, notifications, media, audit logging, and order-status transition rules.

Presentation code remains in controllers and focused Livewire components. Transactional rules live in action classes, and Eloquent models own relationships and query scopes. This keeps the application deployable as one VPS application later without prematurely splitting services.

## Identity and authorization

There is one `users` table and one session-based authentication flow. A user begins as a customer; a seller application grants tenant ownership only after an administrator approves it.

Platform access is represented by `users.platform_role` (`super_admin`, `admin`, or `moderator`). Tenant access is represented by a verified `tenant_members` row with an `owner`, `manager`, or `staff` role. Role values are an eligibility signal, not authorization by themselves: policies and gates check the relevant resource, membership, and permitted transition for every mutation.

## Tenant isolation

All tenant-owned tables carry `tenant_id`. Seller actions resolve the tenant from the authenticated user's active membership; browser input never selects a trusted tenant. Tenant model scopes and policies constrain products, stores, and seller orders. Admin cross-tenant views deliberately use separate platform queries and are protected by platform policies.

Tests will cover both direct route-ID tampering and forged request or Livewire state, proving that a Tenant A member cannot read or mutate Tenant B data.

## Orders and integrity

Checkout creates one customer-facing `orders` record, then one `seller_orders` record per store. `order_items` belong to seller orders and store purchase-time product name, SKU, and unit-price snapshots. Checkout locks product rows inside a PostgreSQL transaction, validates active status and stock, creates all records, and decrements stock atomically. This is the only path that changes stock for an order.

All money is calculated as exact two-decimal strings and stored in PostgreSQL `numeric(12,2)` columns. Cash on Delivery creates a pending payment; providers can later implement the payment contract without altering order data. Parent-order status reflects seller-order fulfilment; cancellation restores the cancelled seller order's stock.

## Deliberate MVP boundaries

Products publish automatically after seller approval. Shipping is a zero-Peso value at checkout. There are no online gateways, payouts, commissions, coupons, variants, warehouse stock, or deployment configuration in this phase.
