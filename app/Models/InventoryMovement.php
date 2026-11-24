<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    use HasUlids;

    protected $fillable = ['inventory_batch_id', 'store_id', 'product_id', 'actor_user_id', 'type', 'reason', 'quantity_change', 'quantity_before', 'quantity_after', 'reference'];

    protected function casts(): array
    {
        return ['quantity_change' => 'integer', 'quantity_before' => 'integer', 'quantity_after' => 'integer'];
    }
}
