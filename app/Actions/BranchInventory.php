<?php

namespace App\Actions;

use App\Models\BranchStock;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The batch ledger is the source of truth. branch_stocks and product totals are compatibility
 * aggregates refreshed after every movement. Callers run inside their own transaction.
 */
class BranchInventory
{
    /**
     * Units of a product (or one variant) on hand at a branch.
     */
    public static function available(string $storeId, string $productId, ?string $variantId, bool $lock = false): int
    {
        $batches = InventoryBatch::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('variant_key', $variantId ?? 'default');
        if ($batches->exists()) {
            return (int) $batches->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))
                ->sum('remaining_quantity');
        }

        return (int) BranchStock::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('variant_key', $variantId ?? 'default')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->value('quantity');
    }

    /**
     * Takes units out of a branch. The guarded update cannot take the count below zero even if
     * two checkouts race, because the database rechecks the quantity on the row it updates.
     */
    public function take(string $storeId, Product $product, ?ProductVariant $variant, int $quantity): array
    {
        $batches = InventoryBatch::query()
            ->where('store_id', $storeId)
            ->where('product_id', $product->id)
            ->where('variant_key', $variant?->id ?? 'default')
            ->where('status', 'active')
            ->where('remaining_quantity', '>', 0)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))
            ->orderByRaw('expires_at IS NULL')
            ->orderBy('expires_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        if ($batches->isEmpty() && ! InventoryBatch::query()->where('store_id', $storeId)->where('product_id', $product->id)->where('variant_key', $variant?->id ?? 'default')->exists()) {
            $legacyQuantity = self::available($storeId, $product->id, $variant?->id, true);
            if ($legacyQuantity > 0) {
                InventoryBatch::query()->create([
                    'store_id' => $storeId, 'product_id' => $product->id, 'product_variant_id' => $variant?->id,
                    'variant_key' => $variant?->id ?? 'default', 'lot_number' => 'LEGACY-'.(string) Str::ulid(),
                    'received_quantity' => $legacyQuantity, 'remaining_quantity' => $legacyQuantity, 'status' => 'active',
                ]);

                return $this->take($storeId, $product, $variant, $quantity);
            }
        }
        if ($batches->sum('remaining_quantity') < $quantity) {
            throw ValidationException::withMessages(['cart' => "{$product->name} is no longer available in that quantity at this branch."]);
        }

        $allocations = [];
        $remaining = $quantity;
        foreach ($batches as $batch) {
            if ($remaining === 0) {
                break;
            }
            $taken = min($remaining, $batch->remaining_quantity);
            $before = $batch->remaining_quantity;
            $batch->decrement('remaining_quantity', $taken);
            InventoryMovement::query()->create([
                'inventory_batch_id' => $batch->id, 'store_id' => $storeId, 'product_id' => $product->id,
                'type' => 'sale', 'reason' => 'marketplace_order', 'quantity_change' => -$taken,
                'quantity_before' => $before, 'quantity_after' => $before - $taken,
            ]);
            $allocations[] = ['batch_id' => $batch->id, 'quantity' => $taken];
            $remaining -= $taken;
        }
        $this->syncBranchStock($storeId, $product, $variant);
        $this->syncTotals($product);

        return $allocations;
    }

    /**
     * Puts units back at a branch: a cancelled order, a restocked return.
     */
    public function put(string $storeId, Product $product, ?ProductVariant $variant, int $quantity): void
    {
        if ($quantity < 1) {
            return;
        }
        $this->ensureLegacyBatch($storeId, $product, $variant);
        $batch = InventoryBatch::query()->create([
            'store_id' => $storeId, 'product_id' => $product->id, 'product_variant_id' => $variant?->id,
            'variant_key' => $variant?->id ?? 'default', 'lot_number' => 'RESTOCK-'.(string) Str::ulid(),
            'received_quantity' => $quantity, 'remaining_quantity' => $quantity, 'status' => 'active',
        ]);
        InventoryMovement::query()->create([
            'inventory_batch_id' => $batch->id, 'store_id' => $storeId, 'product_id' => $product->id,
            'type' => 'restock', 'reason' => 'order_return', 'quantity_change' => $quantity,
            'quantity_before' => 0, 'quantity_after' => $quantity,
        ]);
        $this->syncBranchStock($storeId, $product, $variant);
        $this->syncTotals($product);
    }

    public function restoreAllocations(string $storeId, Product $product, ?ProductVariant $variant, array $allocations, int $fallbackQuantity): void
    {
        if ($allocations === []) {
            $this->put($storeId, $product, $variant, $fallbackQuantity);

            return;
        }
        foreach ($allocations as $allocation) {
            $batch = InventoryBatch::query()->whereKey($allocation['batch_id'])->lockForUpdate()->first();
            if ($batch === null) {
                continue;
            }
            $before = $batch->remaining_quantity;
            $batch->increment('remaining_quantity', $allocation['quantity']);
            InventoryMovement::query()->create([
                'inventory_batch_id' => $batch->id, 'store_id' => $storeId, 'product_id' => $product->id,
                'type' => 'restock', 'reason' => 'order_cancellation', 'quantity_change' => $allocation['quantity'],
                'quantity_before' => $before, 'quantity_after' => $before + $allocation['quantity'],
            ]);
        }
        $this->syncBranchStock($storeId, $product, $variant);
        $this->syncTotals($product);
    }

    public function syncBranchStock(string $storeId, Product $product, ?ProductVariant $variant): void
    {
        $key = $variant?->id ?? 'default';
        $quantity = (int) InventoryBatch::query()->where('store_id', $storeId)->where('product_id', $product->id)
            ->where('variant_key', $key)->where('status', 'active')->where(fn ($query) => $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))
            ->sum('remaining_quantity');
        BranchStock::query()->updateOrCreate(
            ['store_id' => $storeId, 'product_id' => $product->id, 'variant_key' => $key],
            ['product_variant_id' => $variant?->id, 'quantity' => $quantity],
        );
    }

    public function setCount(User $actor, string $storeId, Product $product, ?ProductVariant $variant, int $count, ?string $ipAddress): void
    {
        $this->ensureLegacyBatch($storeId, $product, $variant);
        $key = $variant?->id ?? 'default';
        $batches = InventoryBatch::query()->where('store_id', $storeId)->where('product_id', $product->id)
            ->where('variant_key', $key)->where('status', 'active')->orderByRaw('expires_at IS NULL')->orderBy('expires_at')
            ->lockForUpdate()->get();
        $current = (int) $batches->sum('remaining_quantity');
        // New units need a lot number and expiry date, so they arrive through ReceiveBranchStock, never a count.
        if ($count > $current) {
            throw ValidationException::withMessages(['stock' => "A count can only lower stock. To add units of {$product->name}, receive a batch with its lot number and expiry date."]);
        }
        if ($count < $current) {
            $remove = $current - $count;
            foreach ($batches as $batch) {
                if ($remove === 0) {
                    break;
                }
                $amount = min($remove, $batch->remaining_quantity);
                $before = $batch->remaining_quantity;
                $batch->decrement('remaining_quantity', $amount);
                InventoryMovement::query()->create([
                    'inventory_batch_id' => $batch->id, 'store_id' => $storeId, 'product_id' => $product->id,
                    'actor_user_id' => $actor->id, 'type' => 'adjustment', 'reason' => 'count',
                    'quantity_change' => -$amount, 'quantity_before' => $before, 'quantity_after' => $before - $amount,
                ]);
                $remove -= $amount;
            }
        }
        $this->syncBranchStock($storeId, $product, $variant);
        $this->syncTotals($product);
    }

    public function ensureLegacyBatch(string $storeId, Product $product, ?ProductVariant $variant): void
    {
        $key = $variant?->id ?? 'default';
        if (InventoryBatch::query()->where('store_id', $storeId)->where('product_id', $product->id)->where('variant_key', $key)->exists()) {
            return;
        }
        $quantity = self::available($storeId, $product->id, $variant?->id, true);
        if ($quantity < 1) {
            return;
        }
        InventoryBatch::query()->create([
            'store_id' => $storeId, 'product_id' => $product->id, 'product_variant_id' => $variant?->id,
            'variant_key' => $key, 'lot_number' => 'LEGACY-'.(string) Str::ulid(),
            'received_quantity' => $quantity, 'remaining_quantity' => $quantity, 'status' => 'active',
        ]);
    }

    /**
     * Rewrites the company-wide totals from every branch's count.
     */
    public function syncTotals(Product $product): void
    {
        $byVariant = InventoryBatch::query()
            ->where('product_id', $product->id)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))
            ->selectRaw('variant_key, SUM(remaining_quantity) as total')
            ->groupBy('variant_key')
            ->pluck('total', 'variant_key');

        // Keep compatibility with older seeders and integrations that still create branch_stocks directly.
        $legacy = BranchStock::query()
            ->where('product_id', $product->id)
            ->whereNotIn('variant_key', InventoryBatch::query()->select('variant_key')
                ->whereColumn('product_id', 'branch_stocks.product_id')
                ->whereColumn('store_id', 'branch_stocks.store_id'))
            ->selectRaw('variant_key, SUM(quantity) as total')
            ->groupBy('variant_key')
            ->pluck('total', 'variant_key');
        foreach ($legacy as $variantKey => $quantity) {
            $byVariant[$variantKey] = (int) ($byVariant[$variantKey] ?? 0) + (int) $quantity;
        }

        $variantIds = $product->variants()->pluck('id');
        // Once a product has variants it is sold per variant, so an old whole-product count no longer counts.
        $total = $variantIds->isEmpty() ? (int) ($byVariant['default'] ?? 0) : (int) $byVariant->only($variantIds->all())->sum();

        Product::withTrashed()->whereKey($product->id)->update(['stock_quantity' => $total]);
        foreach ($variantIds as $variantId) {
            ProductVariant::query()->whereKey($variantId)->update(['stock_quantity' => (int) ($byVariant[$variantId] ?? 0)]);
        }
    }
}
