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

class AdjustBranchStock
{
    public function __construct(private BranchInventory $inventory) {}

    public function handle(User $actor, Store $branch, Product $product, string $batchId, int $quantity, string $reason, ?string $ipAddress): void
    {
        DB::transaction(function () use ($actor, $branch, $product, $batchId, $quantity, $reason, $ipAddress): void {
            $product = Product::query()->where('tenant_id', $branch->tenant_id)->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $batch = InventoryBatch::query()->where('store_id', $branch->id)->where('product_id', $product->id)->whereKey($batchId)->lockForUpdate()->firstOrFail();
            $before = $batch->remaining_quantity;
            if ($quantity > $before) {
                throw ValidationException::withMessages(['quantity' => 'An adjustment cannot add units. Receive new stock instead.']);
            }
            $change = $quantity - $before;
            $batch->update(['remaining_quantity' => $quantity, 'status' => $reason === 'quarantine' ? 'quarantined' : $batch->status]);
            InventoryMovement::query()->create([
                'inventory_batch_id' => $batch->id, 'store_id' => $branch->id, 'product_id' => $product->id,
                'actor_user_id' => $actor->id, 'type' => 'adjustment', 'reason' => $reason,
                'quantity_change' => $change, 'quantity_before' => $before, 'quantity_after' => $quantity,
            ]);
            $variant = $batch->product_variant_id === null ? null : $product->variants()->whereKey($batch->product_variant_id)->first();
            $this->inventory->syncBranchStock($branch->id, $product, $variant);
            $this->inventory->syncTotals($product);
            AuditLog::query()->create([
                'actor_user_id' => $actor->id, 'action' => 'branch_stock.adjusted', 'entity_type' => InventoryBatch::class,
                'entity_id' => $batch->id, 'old_values' => ['remaining_quantity' => $before],
                'new_values' => ['remaining_quantity' => $quantity, 'reason' => $reason], 'ip_address' => $ipAddress,
            ]);
        });
    }
}
