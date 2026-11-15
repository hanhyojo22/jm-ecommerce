<?php

namespace App\Models;

use App\Services\PayMongo;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasUlids, SoftDeletes;

    /**
     * Address, shipping and location details the seller gives at onboarding and keeps in store settings.
     *
     * @var list<string>
     */
    public const LOCATION_DETAILS = ['region', 'province', 'barangay', 'postal_code', 'pickup_address', 'return_address', 'processing_days', 'fulfillment_methods', 'delivery_areas', 'latitude', 'longitude', 'delivery_radius_km'];

    protected $fillable = ['tenant_id', 'name', 'slug', 'description', 'logo_path', 'banner_path', 'phone', 'address_line', 'city', 'status', ...self::LOCATION_DETAILS];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'processing_days' => 'integer', 'delivery_radius_km' => 'integer'];
    }

    /**
     * A store takes online payments once PayMongo has activated its child account, because the
     * checkout splits the store's share straight to that account.
     */
    /**
     * Every branch takes online payments once the pharmacy's PayMongo account is set up.
     */
    public function acceptsOnlinePayments(): bool
    {
        return PayMongo::isConfigured();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function branchStocks(): HasMany
    {
        return $this->hasMany(BranchStock::class);
    }

    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
