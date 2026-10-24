<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

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
        'province',
        'postal_code',
        'delivery_note',
        'subtotal',
        'shipping_amount',
        'total',
        'status',
    ];

    /**
     * Order numbers carry a 26-character ULID. Lists show the prefix and the last eight
     * characters, which are what tells orders apart.
     */
    public static function shortNumber(string $number): string
    {
        return mb_strlen($number) > 16 ? Str::before($number, '-').'-…'.Str::substr($number, -8) : $number;
    }

    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
