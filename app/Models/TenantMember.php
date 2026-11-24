<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TenantMember extends Model
{
    use HasUlids;

    /**
     * Days a new employee has to sign in before their place on the team lapses, so a mistyped
     * email cannot hand the pharmacy to a stranger later.
     */
    public const INVITATION_DAYS = 14;

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
     * An employee who never signed in within the invitation window; their next sign-in drops the place.
     */
    public function invitationExpired(): bool
    {
        return ! $this->isOwner() && $this->user?->clerk_user_id === null && $this->created_at->lt(now()->subDays(self::INVITATION_DAYS));
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

    /**
     * Whether this member may change orders: owners and managers anywhere, staff only at their own
     * branch. Head-office staff see every branch's orders but only read them.
     */
    public function canWorkOrders(): bool
    {
        return in_array($this->role, ['owner', 'manager'], true) || $this->store_id !== null;
    }

    /**
     * The job title shown in Seller Center: a manager tied to one branch is a branch manager.
     */
    public function roleLabel(): string
    {
        return match ($this->role) {
            'owner' => $this->isHeadOffice() ? 'Admin' : 'Owner',
            'manager' => $this->isHeadOffice() ? 'Manager' : 'Branch manager',
            default => 'Staff',
        };
    }

    /**
     * Where this member works, e.g. "Ermita branch" for "Everyday Pharmacy Ermita", or "Head office".
     */
    public function placeLabel(): string
    {
        if ($this->store === null) {
            return 'Head office';
        }

        $place = trim(Str::after($this->store->name, $this->tenant->name)) ?: $this->store->name;

        if (in_array(Str::lower($place), ['store', 'main store', 'branch', 'main branch'], true)) {
            $place = filled($this->store->city) ? $this->store->city : $this->store->name;
        }

        return Str::endsWith(Str::lower($place), 'branch') ? $place : "{$place} branch";
    }

    /**
     * Where a pharmacist's license stands, so the workspace can say why they can't review yet.
     * Null for members who are not pharmacists; "expiring" still allows reviews.
     *
     * @return 'missing'|'expired'|'expiring'|'valid'|null
     */
    public function licenseStatus(): ?string
    {
        return match (true) {
            ! $this->is_pharmacist => null,
            blank($this->license_number) || $this->license_expires_on === null => 'missing',
            $this->license_expires_on->isBefore(today()) => 'expired',
            $this->license_expires_on->isBefore(today()->addDays(30)) => 'expiring',
            default => 'valid',
        };
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
