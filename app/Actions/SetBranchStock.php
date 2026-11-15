<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetBranchStock
{
    /**
     * Records a stock count for one branch. Only the company's own products count, and a
     * product with variants is counted per variant, never as a whole.
     *
     * @param  array<string, array<string, int>>  $counts  product id => variant key => units on hand
     * @return int how many counts changed
     */
    public function handle(User $actor, Store $branch, array $counts, ?string $ipAddress = null): int
    {
        return DB::transaction(function () use ($actor, $branch, $counts, $ipAddress): int {
            $products = Product::query()
                ->where('tenant_id', $branch->tenant_id)
                ->whereIn('id', array_keys($counts))
                ->with('variants:id,product_id')
                ->get()
                ->keyBy('id');

            if ($products->count() !== count($counts)) {
                throw ValidationException::withMessages(['stock' => 'Some of these products are no longer in your catalog. Refresh the page and try again.']);
            }

            $existing = BranchStock::query()
                ->where('store_id', $branch->id)
                ->whereIn('product_id', $products->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (BranchStock $stock): string => $stock->product_id.'|'.$stock->variant_key);

            $changes = [];
            foreach ($counts as $productId => $variantCounts) {
                $product = $products[$productId];
                $variantIds = $product->variants->pluck('id')->all();
                $allowedKeys = $variantIds === [] ? ['default'] : $variantIds;

                foreach ($variantCounts as $variantKey => $quantity) {
                    if (! in_array($variantKey, $allowedKeys, true)) {
                        throw ValidationException::withMessages(['stock' => "Refresh the page: {$product->name} has different options now."]);
                    }

                    $stock = $existing[$productId.'|'.$variantKey] ?? null;
                    if ($stock?->quantity === $quantity || ($stock === null && $quantity === 0)) {
                        continue;
                    }

                    BranchStock::query()->updateOrCreate(
                        ['store_id' => $branch->id, 'product_id' => $productId, 'variant_key' => $variantKey],
                        ['product_variant_id' => $variantKey === 'default' ? null : $variantKey, 'quantity' => $quantity],
                    );
                    $changes[] = ['product_id' => $productId, 'variant_key' => $variantKey, 'from' => $stock?->quantity ?? 0, 'to' => $quantity];
                }
            }

            if ($changes !== []) {
                AuditLog::query()->create([
                    'actor_user_id' => $actor->id,
                    'action' => 'branch_stock.counted',
                    'entity_type' => Store::class,
                    'entity_id' => $branch->id,
                    'new_values' => ['changes' => $changes],
                    'ip_address' => $ipAddress,
                ]);
            }

            return count($changes);
        });
    }
}
