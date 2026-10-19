<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SellerOrder extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'subtotal',
        'shipping_amount',
        'total',
        'status',
        'delivered_at',
        'cod_collected_at',
    ];

    protected function casts(): array
    {
        return ['delivered_at' => 'datetime', 'cod_collected_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(OrderIssue::class);
    }

    public function returnCases(): HasMany
    {
        return $this->hasMany(ReturnCase::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function earning(): HasOne
    {
        return $this->hasOne(SellerEarning::class);
    }
}
