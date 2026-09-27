<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'number',
        'recipient_name',
        'phone',
        'address_line',
        'city',
        'subtotal',
        'shipping_amount',
        'total',
        'status',
    ];

    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
