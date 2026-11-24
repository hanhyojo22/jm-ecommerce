<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceiveBranchStock
{
    public function __construct(private BranchInventory $inventory) {}

    public function handle(User $actor, Store $branch, Product $product, ?string $variantId, array $data, ?string $ipAddress): InventoryBatch
    {
        return DB::transaction(function () use ($actor, $branch, $product, $variantId, $data, $ipAddress): InventoryBatch {
            $product = Product::query()->where('tenant_id', $branch->tenant_id)->whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ($product->variants()->exists() !== ($variantId !== null)) {
                throw ValidationException::withMessages(['variant_id' => 'Choose a valid product option for this medicine.']);
            }
            $variant = $variantId === null ? null : $product->variants()->whereKey($variantId)->firstOrFail();
            $this->inventory->ensureLegacyBatch($branch->id, $product, $variant);
            $batch = InventoryBatch::query()->create([
                'store_id' => $branch->id, 'product_id' => $product->id, 'product_variant_id' => $variant?->id,
                'variant_key' => $variant?->id ?? 'default', 'lot_number' => $data['lot_number'],
                'expires_at' => $data['expires_at'] ?? null, 'received_quantity' => $data['quantity'],
                'remaining_quantity' => $data['quantity'], 'status' => 'active', 'supplier' => $data['supplier'] ?? null,
                'reference' => $data['reference'] ?? null,
            ]);
            InventoryMovement::query()->create([
                'inventory_batch_id' => $batch->id, 'store_id' => $branch->id, 'product_id' => $product->id,
                'actor_user_id' => $actor->id, 'type' => 'received', 'reason' => 'supplier_delivery',
                'quantity_change' => $data['quantity'], 'quantity_before' => 0, 'quantity_after' => $data['quantity'],
                'reference' => $data['reference'] ?? null,
            ]);
            $this->inventory->syncBranchStock($branch->id, $product, $variant);
            $this->inventory->syncTotals($product);
            AuditLog::query()->create([
                'actor_user_id' => $actor->id, 'action' => 'branch_stock.received', 'entity_type' => InventoryBatch::class,
                'entity_id' => $batch->id, 'new_values' => $data + ['branch_id' => $branch->id, 'product_id' => $product->id], 'ip_address' => $ipAddress,
            ]);

            return $batch;
        });
    }
}
