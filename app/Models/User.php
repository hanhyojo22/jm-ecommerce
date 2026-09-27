<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'account_type', 'platform_role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlids, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function tenantMemberships(): HasMany
    {
        return $this->hasMany(TenantMember::class);
    }

    public function sellerApplications(): HasMany
    {
        return $this->hasMany(SellerApplication::class);
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isPlatformAdministrator(): bool
    {
        return $this->account_type === 'admin' && in_array($this->platform_role, ['super_admin', 'admin'], true);
    }

    public function isOwner(): bool
    {
        return $this->account_type === 'admin' && $this->platform_role === 'super_admin';
    }

    public function landingRouteName(): string
    {
        if ($this->account_type === 'admin') {
            return 'dashboard';
        }

        if ($this->account_type === 'seller') {
            return $this->tenantMemberships()->exists()
                ? 'seller.dashboard'
                : 'seller-application.create';
        }

        return 'catalog.index';
    }

    public function cartItemCount(): int
    {
        if ($this->account_type !== 'buyer') {
            return 0;
        }

        $cart = $this->cart()->withSum('items', 'quantity')->first();

        return (int) ($cart?->items_sum_quantity ?? 0);
    }

    /**
     * Resolve the seller tenant used by the current MVP seller workspace.
     *
     * @param  array<int, string>  $roles
     */
    public function sellerTenant(array $roles = ['owner', 'manager', 'staff']): ?Tenant
    {
        $membership = $this->tenantMemberships()
            ->whereIn('role', $roles)
            ->with(['tenant.store'])
            ->whereHas('tenant', fn ($query) => $query
                ->where('status', 'active')
                ->whereHas('store', fn ($store) => $store->where('status', 'active')))
            ->oldest('created_at')
            ->first();

        return $membership?->tenant;
    }
}
