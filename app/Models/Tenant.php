<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = ['name', 'slug', 'status', 'platform_fee_accepted_at', 'platform_fee_policy_version', 'platform_fee_rate'];

    protected function casts(): array
    {
        return ['platform_fee_accepted_at' => 'datetime', 'platform_fee_rate' => 'decimal:4'];
    }

    public function hasAcceptedCurrentFeeTerms(): bool
    {
        return $this->platform_fee_accepted_at !== null
            && $this->platform_fee_policy_version === (string) config('marketplace.platform_fee.policy_version');
    }

    /**
     * The rate an order delivered at the given time is charged: nothing before the store agreed to
     * the fee terms, then the lower of the rate it agreed to and the current one, so a rate cut
     * applies at once and a rise only once the store accepts it.
     */
    public function platformFeeRateAt(CarbonInterface $deliveredAt): string
    {
        if ($this->platform_fee_accepted_at === null || $this->platform_fee_accepted_at->gt($deliveredAt)) {
            return '0';
        }

        $current = (string) config('marketplace.platform_fee.rate');
        $agreed = (string) ($this->platform_fee_rate ?? $current);

        return bccomp($agreed, $current, 4) < 0 ? $agreed : $current;
    }

    public function members(): HasMany
    {
        return $this->hasMany(TenantMember::class);
    }

    public function store(): HasOne
    {
        return $this->hasOne(Store::class);
    }

    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    public function sellerEarnings(): HasMany
    {
        return $this->hasMany(SellerEarning::class);
    }

    public function sellerPayouts(): HasMany
    {
        return $this->hasMany(SellerPayout::class);
    }

    public function platformFeeStatements(): HasMany
    {
        return $this->hasMany(PlatformFeeStatement::class);
    }
}
