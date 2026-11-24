<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBatch extends Model
{
    use HasUlids;

    protected $fillable = ['store_id', 'product_id', 'product_variant_id', 'variant_key', 'lot_number', 'expires_at', 'received_quantity', 'remaining_quantity', 'status', 'supplier', 'reference'];

    protected function casts(): array
    {
        return ['expires_at' => 'date', 'received_quantity' => 'integer', 'remaining_quantity' => 'integer'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(InventoryAllocation::class);
    }
}
