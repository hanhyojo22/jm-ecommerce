<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'clerk_user_id', 'account_type', 'platform_role', 'admin_invited_at', 'is_active', 'preferred_workspace'])]
#[Hidden(['password', 'remember_token', 'clerk_user_id'])]
class User extends Authenticatable
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
            'admin_invited_at' => 'datetime',
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

    public function deliveryAddresses(): HasMany
    {
        return $this->hasMany(DeliveryAddress::class);
    }

    public function productReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'buyer_id');
    }

    public function sentConversationMessages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class, 'sender_id');
    }

    public function isPlatformAdministrator(): bool
    {
        return $this->account_type === 'admin' && in_array($this->platform_role, ['super_admin', 'admin'], true);
    }

    public function hasVerifiedClerkEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function isOwner(): bool
    {
        return $this->account_type === 'admin' && $this->platform_role === 'super_admin';
    }

    public function landingRouteName(): string
    {
        if ($this->isPlatformAdministrator()) {
            return 'dashboard';
        }

        if ($this->account_type === 'admin') {
            return 'catalog.index';
        }

        if ($this->account_type === 'seller') {
            if ($this->preferred_workspace === 'seller' && $this->sellerTenant() !== null) {
                return 'seller.dashboard';
            }

            return $this->preferred_workspace === 'customer' ? 'home' : 'seller-application.create';
        }

        return 'catalog.index';
    }

    public function cartItemCount(): int
    {
        if (! in_array($this->account_type, ['buyer', 'seller'], true)) {
            return 0;
        }

        $cart = $this->cart()->withSum('items', 'quantity')->first();

        return (int) ($cart?->items_sum_quantity ?? 0);
    }

    /**
     * Membership is asked once per product when a cart is read, so the answer is kept for the
     * life of the instance rather than costing a query per item.
     *
     * @var array<string, true>|null
     */
    private ?array $staffTenantIds = null;

    public function isMemberOfTenant(string $tenantId): bool
    {
        $this->staffTenantIds ??= $this->tenantMemberships()
            ->whereIn('role', ['owner', 'manager', 'staff'])
            ->pluck('tenant_id')
            ->flip()
            ->map(fn (): bool => true)
            ->all();

        return isset($this->staffTenantIds[$tenantId]);
    }

    /**
     * Drops the remembered memberships so a role change made during this request is seen.
     */
    public function forgetTenantMemberships(): void
    {
        $this->staffTenantIds = null;
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
