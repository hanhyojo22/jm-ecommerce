<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Store
 */
class StoreResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'city' => $this->city,
            'province' => $this->province,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'delivery_radius_km' => $this->delivery_radius_km,
            'logo_url' => $this->logo_path === null ? null : Storage::disk('public')->url($this->logo_path),
            'banner_url' => $this->banner_path === null ? null : Storage::disk('public')->url($this->banner_path),
            'accepts_online_payments' => $this->acceptsOnlinePayments(),
            'products_count' => $this->when(isset($this->public_products_count), fn (): int => (int) $this->public_products_count),
            'rating' => $this->when(isset($this->ratings_count), fn (): array => self::ratingSummary($this->resource)),
        ];
    }

    /**
     * The store's rating from buyers; needs `withCount('ratings')` and `withAvg('ratings', 'rating')`.
     *
     * @return array{average: float, count: int}
     */
    public static function ratingSummary(Store $store): array
    {
        return [
            'average' => round((float) ($store->ratings_avg_rating ?? 0), 2),
            'count' => (int) $store->ratings_count,
        ];
    }
}
