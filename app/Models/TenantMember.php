<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantMember extends Model
{
    use HasUlids;

    protected $fillable = ['tenant_id', 'store_id', 'user_id', 'role'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The branch this member works at; null for head office, who work across every branch.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isHeadOffice(): bool
    {
        return $this->store_id === null;
    }

    /**
     * Orders this member may see: every branch's for head office, their own branch's otherwise.
     */
    public function sellerOrders(): HasMany
    {
        return $this->tenant->sellerOrders()->when($this->store_id, fn (Builder $query, string $storeId): Builder => $query->where('store_id', $storeId));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
