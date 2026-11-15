<?php

namespace App\Actions;

use App\Models\BranchStock;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

/**
 * The one place stock moves. Each branch's count in branch_stocks is the truth; a product's and
 * variant's stock_quantity is the company-wide total, recomputed after every move so it never drifts.
 * Callers run inside their own transaction.
 */
class BranchInventory
{
    /**
     * Units of a product (or one variant) on hand at a branch.
     */
    public static function available(string $storeId, string $productId, ?string $variantId, bool $lock = false): int
    {
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
    public function take(string $storeId, Product $product, ?ProductVariant $variant, int $quantity): void
    {
        $taken = BranchStock::query()
            ->where('store_id', $storeId)
            ->where('product_id', $product->id)
            ->where('variant_key', $variant?->id ?? 'default')
            ->where('quantity', '>=', $quantity)
            ->decrement('quantity', $quantity);

        if ($taken === 0) {
            throw ValidationException::withMessages(['cart' => "{$product->name} is no longer available in that quantity at this branch."]);
        }

        $this->syncTotals($product);
    }

    /**
     * Puts units back at a branch: a cancelled order, a restocked return.
     */
    public function put(string $storeId, Product $product, ?ProductVariant $variant, int $quantity): void
    {
        $stock = BranchStock::query()->firstOrCreate(
            ['store_id' => $storeId, 'product_id' => $product->id, 'variant_key' => $variant?->id ?? 'default'],
            ['product_variant_id' => $variant?->id, 'quantity' => 0],
        );
        $stock->increment('quantity', $quantity);

        $this->syncTotals($product);
    }

    /**
     * Rewrites the company-wide totals from every branch's count.
     */
    public function syncTotals(Product $product): void
    {
        $byVariant = BranchStock::query()
            ->where('product_id', $product->id)
            ->selectRaw('variant_key, SUM(quantity) as total')
            ->groupBy('variant_key')
            ->pluck('total', 'variant_key');

        $variantIds = $product->variants()->pluck('id');
        // Once a product has variants it is sold per variant, so an old whole-product count no longer counts.
        $total = $variantIds->isEmpty() ? (int) ($byVariant['default'] ?? 0) : (int) $byVariant->only($variantIds->all())->sum();

        Product::withTrashed()->whereKey($product->id)->update(['stock_quantity' => $total]);
        foreach ($variantIds as $variantId) {
            ProductVariant::query()->whereKey($variantId)->update(['stock_quantity' => (int) ($byVariant[$variantId] ?? 0)]);
        }
    }
}
