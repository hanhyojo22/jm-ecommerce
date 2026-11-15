<?php

namespace Database\Seeders;

use App\Models\BranchStock;
use App\Models\Product;
use Illuminate\Database\Seeder;

class BranchStockSeeder extends Seeder
{
    /**
     * Puts each seeded product's stock at its first branch, per variant when it has variants,
     * so sample and browser-test catalogs have something to buy.
     */
    public function run(): void
    {
        Product::query()->with('variants')->each(function (Product $product): void {
            $counts = $product->variants->isEmpty()
                ? ['default' => [null, (int) $product->stock_quantity]]
                : $product->variants->mapWithKeys(fn ($variant): array => [$variant->id => [$variant->id, (int) $variant->stock_quantity]])->all();

            foreach ($counts as $variantKey => [$variantId, $quantity]) {
                BranchStock::query()->updateOrCreate(
                    ['store_id' => $product->store_id, 'product_id' => $product->id, 'variant_key' => $variantKey],
                    ['product_variant_id' => $variantId, 'quantity' => $quantity],
                );
            }
        });
    }
}
