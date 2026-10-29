<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasUlids;

    /** Paid online through one PayMongo checkout that splits the money between the stores. */
    public const ONLINE_METHODS = ['gcash', 'maya', 'card'];

    /** The PayMongo payment method type each online method is limited to at checkout. */
    public const PAYMONGO_TYPES = ['gcash' => 'gcash', 'maya' => 'paymaya', 'card' => 'card'];

    // Bank transfer stays labelled for orders placed before online payments moved to PayMongo.
    public const METHOD_LABELS = ['cod' => 'Cash on delivery', 'gcash' => 'GCash', 'maya' => 'Maya', 'card' => 'Card', 'bank' => 'Bank transfer'];

    protected $fillable = ['method', 'status', 'amount', 'fee_amount', 'checkout_session_id', 'gateway_payment_id'];

    public function isOnline(): bool
    {
        return in_array($this->method, [...self::ONLINE_METHODS, 'bank'], true);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PaymentAdjustment::class);
    }
}
