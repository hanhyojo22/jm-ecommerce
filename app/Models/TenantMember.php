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

    protected $fillable = ['tenant_id', 'store_id', 'user_id', 'role', 'is_pharmacist', 'license_number', 'license_expires_on'];

    protected function casts(): array
    {
        return ['is_pharmacist' => 'boolean', 'license_expires_on' => 'date'];
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /**
     * Only a pharmacist with a PRC license on file that has not expired may open or decide prescriptions (RA 10918).
     */
    public function canReviewPrescriptions(): bool
    {
        return $this->is_pharmacist
            && filled($this->license_number)
            && $this->license_expires_on !== null
            && ! $this->license_expires_on->isBefore(today());
    }

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
