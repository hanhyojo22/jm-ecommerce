---
name: jmmart-tenant-isolation
description: Use when writing or changing any JMMart seller-side code (app/Http/Controllers/Seller, seller views, seller routes) or anything that reads or mutates tenant-owned records such as products, variants, stores, seller orders, earnings, payouts, return cases, or platform fee statements.
---

# JMMart Tenant Isolation

## Overview
Every seller-owned record carries `tenant_id`. The tenant is always resolved from the signed-in user's membership, never from request input or a route-bound model. A seller who guesses another tenant's ID gets **404**, not 403.

## Core Pattern

```php
// Controller: resolve the tenant from the user, 403 if they have none.
private function activeTenant(Request $request, array $roles = ['owner', 'manager', 'staff']): Tenant
{
    return $request->user()->sellerTenant($roles) ?? abort(403);
}

public function show(Request $request, SellerOrder $sellerOrder): View
{
    $tenant = $this->activeTenant($request);

    // Re-query the bound model THROUGH the tenant relation → cross-tenant = 404.
    $sellerOrder = $tenant->sellerOrders()->whereKey($sellerOrder)->firstOrFail();
}

// Creating: tenant_id/store_id come from $tenant, never from $request.
$tenant->products()->create([...$validated, 'store_id' => $tenant->store->id]);
```

## Quick Reference

| Need | Do |
|------|----|
| Current tenant | `$request->user()->sellerTenant()` (only active tenant + active store count) |
| Restrict by role | `sellerTenant(['owner', 'manager'])` |
| Load a route-bound record | `$tenant->relation()->whereKey($model)->firstOrFail()` |
| Membership check elsewhere | `$user->isMemberOfTenant($tenantId)` / policies in `app/Policies` |
| Seller route group | `['auth', 'clerk.verified', 'account:seller']` + `throttle:10,1` on mutations |
| Admin cross-tenant view | Separate query in `app/Queries/Admin*Query`, guarded by `isPlatformAdministrator()` |

## Common Mistakes
- Using the route-bound `$product` directly after a `$product->tenant_id === ...` check — inconsistent and easy to forget on one method. Re-query through the tenant relation.
- Accepting `tenant_id`, `store_id`, or `seller_id` in validated input. Strip them; derive from `$tenant`.
- Livewire: public properties holding IDs are user-editable. Re-resolve and re-scope on every action.
- Bulk actions (`bulk-deactivate`, etc.): scope the `whereIn('id', ...)` through `$tenant->products()`, or foreign IDs get silently changed.
- Returning 403 for another tenant's record leaks that it exists. Tests expect `assertNotFound()`.

## Required Test
Any new seller endpoint gets a two-tenant test: seller A requests tenant B's record, asserts `assertNotFound()`, and (for mutations) `assertDatabaseHas` proves B's row is unchanged. See `jmmart-feature-tests`.
