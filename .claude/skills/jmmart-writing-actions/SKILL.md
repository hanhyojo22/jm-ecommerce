---
name: jmmart-writing-actions
description: Use when adding or changing a JMMart state-changing business operation — order or seller-order status changes, stock changes, payments/COD, earnings, payouts, platform fees, store suspension, seller application review, returns, or anything an admin does that should leave an audit trail.
---

# JMMart Action Classes

## Overview
Transactional rules live in `app/Actions/*` classes with a single public `handle()` method. Controllers and Livewire components validate, authorize, call the action, and turn its exceptions into flash errors. Models own relationships and scopes only.

## Core Pattern

```php
class SuspendStore
{
    public function __construct(private RecordSellerEarning $recordSellerEarning) {} // DI only if needed

    public function handle(User $administrator, Store $store, string $reason, ?string $ipAddress = null): Store
    {
        return DB::transaction(function () use (...): Store {
            // 1. Re-fetch under lock — never trust the passed-in instance's state.
            $store = Store::query()->lockForUpdate()->findOrFail($store->id);

            // 2. Guard the transition.
            if ($store->status === 'suspended') {
                throw new LogicException('This store is already suspended.');
            }

            // 3. Mutate.
            $store->update(['status' => 'suspended']);

            // 4. Audit sensitive/admin changes.
            AuditLog::query()->create([
                'actor_user_id' => $administrator->id,
                'action' => 'store.suspended',           // dotted: entity.verb
                'entity_type' => Store::class,
                'entity_id' => $store->id,
                'old_values' => [...], 'new_values' => [...],  // no secrets
                'ip_address' => $ipAddress,
            ]);

            return $store;
        });
    }
}
```

Controller side:
```php
try {
    $action->handle($request->user(), $store, $validated['reason'], $request->ip());
} catch (LogicException $exception) {
    return back()->withErrors(['status' => $exception->getMessage()]);
}
return to_route('admin.stores.show', $store)->with('status', 'Store suspended.');
```

## Quick Reference

| Topic | Convention |
|-------|-----------|
| Create | `php artisan make:class Actions/VerbNoun --no-interaction` |
| Invoke | Method-inject into controller: `fn(..., SuspendStore $action)` |
| Statuses | Plain strings (`'pending'`, `'shipped'`), no PHP enums |
| Transitions | `private const TRANSITIONS = ['pending' => ['confirmed', 'cancelled'], ...]` + static `transitionsFor()` |
| User-facing rule failure | `ValidationException::withMessages(['field' => '...'])` |
| State conflict | `LogicException`, caught in controller |
| Lock order | Parent first (`Order`), then children ordered by id (`sellerOrders()->orderBy('id')->lockForUpdate()`) |
| System actor | `?User $actor = null` means JMMart itself (e.g. auto-complete) — document in PHPDoc |

## Common Mistakes
- Checking status on the unlocked model, then updating → race. Lock and re-read inside the transaction.
- Changing stock outside an action. Only `CheckoutCart` decrements; `UpdateSellerOrderStatus` (cancel) and `ReturnCaseWorkflow` increment. Product **and** variant stock move together.
- Sending mail/broadcasts inside the transaction. Do side effects after it commits.
- Forgetting the parent `orders.status` — it is derived from its seller orders; reuse the existing recompute in `UpdateSellerOrderStatus`.
- Money math: see `jmmart-money-and-orders`.
