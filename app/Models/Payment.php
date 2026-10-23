<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasUlids;

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
