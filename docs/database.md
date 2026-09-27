# JMMart database plan

All marketplace identifiers use ULIDs. They are sortable, non-sequential public identifiers and work well across the modular monolith.

| Area | Tables | Important relationships |
| --- | --- | --- |
| Identity | `users`, `tenant_members` | A user may have many tenant memberships; one membership grants a tenant role. |
| Seller onboarding | `seller_applications`, `tenants`, `stores` | Approval creates an active tenant, an owner membership, and one store. |
| Catalogue | `categories`, `products`, `product_images` | A product belongs to one tenant, store, and category; images belong to a product. |
| Buying | `carts`, `cart_items` | A customer has one active cart; cart items reference products only until checkout. |
| Orders | `orders`, `seller_orders`, `order_items`, `payments` | One order has many seller orders; one seller order has its immutable item snapshots and one COD payment initially. |
| Governance | `audit_logs` | Records sensitive platform actions without secrets or passwords. |

## Integrity rules

- Tenant-owned records are indexed by `tenant_id`; storefront search also indexes store, category, status, and creation date.
- Product SKU is unique inside a tenant, not globally.
- Cart prices are never trusted. Checkout takes the current server-side price and writes it into an order-item snapshot.
- Order and payment records are not soft-deleted. Products, stores, and categories may be deactivated rather than erased.
- `seller_orders` retain subtotal, shipping amount, and total so commissions and payouts can be introduced later without rewriting history.
