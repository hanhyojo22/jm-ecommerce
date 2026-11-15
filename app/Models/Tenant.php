<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Tenant extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = ['name', 'slug', 'status', 'delivery_fee', 'free_delivery_minimum'];

    protected function casts(): array
    {
        return ['delivery_fee' => 'decimal:2', 'free_delivery_minimum' => 'decimal:2'];
    }

    public function members(): HasMany
    {
        return $this->hasMany(TenantMember::class);
    }

    /**
     * The first branch. Products still hang off it until the catalog reads branch stock.
     */
    public function store(): HasOne
    {
        return $this->hasOne(Store::class)->oldestOfMany();
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    /**
     * Active owner/manager/staff to notify about an order at one branch: head office and that
     * branch's own staff, so a deactivated member, a suspended company, or another branch's
     * staff never get paged. NotifySellersOfNewOrder and OpenOrderIssue share this filter.
     *
     * @return Collection<int, User>
     */
    public function activeNotifiableStaff(string $storeId): Collection
    {
        if ($this->status !== 'active') {
            return new Collection;
        }

        return User::query()
            ->where('account_type', 'seller')
            ->where('is_active', true)
            ->whereHas('tenantMemberships', fn ($query) => $query
                ->where('tenant_id', $this->id)
                ->whereIn('role', ['owner', 'manager', 'staff'])
                // Same rule as the order policies: staff of a closed branch can no longer open its orders.
                ->where(fn ($query) => $query
                    ->whereNull('store_id')
                    ->orWhere(fn ($branch) => $branch->where('store_id', $storeId)->whereHas('store', fn ($store) => $store->where('status', 'active')))))
            ->get();
    }
}
