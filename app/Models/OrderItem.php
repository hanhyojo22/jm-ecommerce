<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'product_id',
        'product_name',
        'sku',
        'unit_price',
        'quantity',
        'line_total',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
