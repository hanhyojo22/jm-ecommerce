<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'product_name',
        'sku',
        'variant_label',
        'unit_price',
        'quantity',
        'line_total',
        'image_path',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(ProductReview::class);
    }
}
