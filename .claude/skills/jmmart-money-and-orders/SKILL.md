---
name: jmmart-money-and-orders
description: Use when JMMart code computes or stores prices, totals, subtotals, fees, earnings, payouts, refunds, or quantities of stock, or touches checkout, carts, order items, COD payments, or the monthly platform fee statement.
---

# JMMart Money and Orders

## Overview
Money is a two-decimal **string**, computed with bcmath, stored in `numeric(12,2)`. Never a float, never an int of pesos. Orders are immutable snapshots of what the buyer saw at checkout.

## Money Rules

```php
bcmul($unitPrice, (string) $quantity, 2);                       // line total
$lines->reduce(fn (string $t, string $l): string => bcadd($t, $l, 2), '0.00');
PlatformFee::on($amount, $rate);                                // rounds half-up to the centavo
```

| Do | Don't |
|----|-------|
| `(string) $model->price` before bc functions | `$price * $qty`, `round()`, `number_format()` for math |
| `'0.00'` as the zero value | `0` / `null` |
| `bccomp($a, $b, 2)` to compare | `$a > $b` on strings |
| Format only in Blade, at the edge | Format then compute |

`bcadd`/`bcmul` truncate. If you need rounding, follow `App\Support\PlatformFee::on` (add `0.005`, valid only for non-negative amounts).

## Order Model

```
Order (buyer-facing, number, address snapshot, totals)
 ├─ Payment (COD, method 'cod', status pending|paid|failed|refunded)
 └─ SellerOrder (one per store, tenant_id, subtotal/shipping/total, status)
     └─ OrderItem (product_name, sku, variant_label, unit price, image_path SNAPSHOTS)
```

- `CheckoutCart` is the **only** path that creates orders and decrements stock. It locks products and variants, re-reads current server prices (cart prices are never trusted), and writes snapshots.
- A product with variants must be bought as a variant; product stock and variant stock move together.
- Cancelling a seller order or accepting a return restores stock (`UpdateSellerOrderStatus`, `ReturnCaseWorkflow`).
- Shipping is `'0.00'`. COD only — no gateway, no refunds API. Don't simulate ones that don't exist.
- Stores collect COD cash themselves; JMMart's platform fee on delivered orders is billed via monthly `PlatformFeeStatement` (`IssuePlatformFeeStatements`), not deducted from payouts.
- Orders and payments are never soft-deleted or edited after the fact; corrections go through `PaymentAdjustment` / audit-logged actions.

## Common Mistakes
- Reading `$item->product->price` to display a past order — use the `order_items` snapshot columns.
- Test assertions with floats: assert `'500.00'`, not `500`.
- Adding a new money column without `decimal(12, 2)` in the migration.
