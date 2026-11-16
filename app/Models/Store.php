<?php

namespace App\Models;

use App\Services\PayMongo;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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

    /**
     * Daily hours; the branch's own manager may set these.
     *
     * @var list<string>
     */
    public const HOURS = ['opening_time', 'closing_time'];

    /**
     * Branch hours are local Philippine time; the app itself runs in UTC.
     */
    public const TIMEZONE = 'Asia/Manila';

    protected $fillable = ['tenant_id', 'name', 'slug', 'description', 'logo_path', 'banner_path', 'phone', 'address_line', 'city', 'status', ...self::LOCATION_DETAILS, ...self::HOURS];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'processing_days' => 'integer', 'delivery_radius_km' => 'integer'];
    }

    /**
     * Open between opening and closing time each day; a closing time before the opening time runs
     * past midnight. A branch with no hours set is always open.
     */
    public function isOpenAt(CarbonInterface $at): bool
    {
        if ($this->opening_time === null || $this->closing_time === null) {
            return true;
        }

        $time = CarbonImmutable::instance($at)->setTimezone(self::TIMEZONE)->format('H:i');

        return $this->opening_time < $this->closing_time
            ? $time >= $this->opening_time && $time < $this->closing_time
            : $time >= $this->opening_time || $time < $this->closing_time;
    }

    /**
     * When a closed branch next opens, in Manila time; null while it is open.
     */
    public function nextOpeningAt(CarbonInterface $at): ?CarbonImmutable
    {
        if ($this->isOpenAt($at)) {
            return null;
        }

        $local = CarbonImmutable::instance($at)->setTimezone(self::TIMEZONE);
        $today = $local->setTimeFromTimeString($this->opening_time);

        return $today->greaterThan($local) ? $today : $today->addDay();
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
