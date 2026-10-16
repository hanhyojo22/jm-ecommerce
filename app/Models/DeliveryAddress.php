<?php

namespace App\Models;

use Database\Factories\DeliveryAddressFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAddress extends Model
{
    /** @use HasFactory<DeliveryAddressFactory> */
    use HasFactory, HasUlids;

    protected $fillable = ['recipient_name', 'phone', 'address_line', 'city', 'province', 'postal_code'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
