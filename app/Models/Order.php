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
        'client_request_id',
        'client_request_hash',
        'recipient_name',
        'phone',
        'address_line',
        'city',
        'province',
        'postal_code',
        'delivery_note',
        'subtotal',
        'shipping_amount',
        'discount_amount',
        'discount_card_type',
        'discount_card_number',
        'discount_card_fingerprint',
        'discount_card_name',
        'total',
        'status',
        'fulfillment_method',
        'delivery_latitude',
        'delivery_longitude',
    ];

    /**
     * The SC/PWD card number never rides along when an order is serialized; screens read it on purpose.
     *
     * @var list<string>
     */
    protected $hidden = ['discount_card_number', 'discount_card_fingerprint', 'discount_document_path', 'discount_document_name', 'discount_document_mime'];

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'shipping_amount' => 'decimal:2', 'discount_amount' => 'decimal:2', 'discount_card_number' => 'encrypted', 'total' => 'decimal:2', 'delivery_latitude' => 'float', 'delivery_longitude' => 'float'];
    }

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

    public function prescription(): HasOne
    {
        return $this->hasOne(Prescription::class);
    }
}
