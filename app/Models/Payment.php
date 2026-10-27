<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasUlids;

    /** Paid straight into the store's own GCash, Maya, or bank account; the store confirms it arrived. */
    public const DIRECT_METHODS = ['gcash', 'maya', 'bank'];

    public const METHOD_LABELS = ['cod' => 'Cash on delivery', 'gcash' => 'GCash', 'maya' => 'Maya', 'bank' => 'Bank transfer'];

    protected $fillable = ['method', 'status', 'amount'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PaymentAdjustment::class);
    }
}
