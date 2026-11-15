<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How many units of one product (or one of its variants) a branch has on hand.
 */
class BranchStock extends Model
{
    use HasUlids;

    protected $fillable = ['store_id', 'product_id', 'product_variant_id', 'variant_key', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
