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
            'delivery_fee' => $this->whenLoaded('tenant', fn (): string => (string) $this->tenant->delivery_fee),
            'free_delivery_minimum' => $this->whenLoaded('tenant', fn (): ?string => $this->tenant->free_delivery_minimum === null ? null : (string) $this->tenant->free_delivery_minimum),
            'products_count' => $this->when(isset($this->public_products_count), fn (): int => (int) $this->public_products_count),
        ];
    }
}
