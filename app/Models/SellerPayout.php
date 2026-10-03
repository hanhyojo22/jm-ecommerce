<?php

namespace App\Models;

use Database\Factories\SellerPayoutFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerPayout extends Model
{
    /** @use HasFactory<SellerPayoutFactory> */
    use HasFactory, HasUlids;

    protected $fillable = ['tenant_id', 'store_id', 'recorded_by', 'reference', 'amount', 'status', 'note', 'recorded_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'recorded_at' => 'datetime'];
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(SellerEarning::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
