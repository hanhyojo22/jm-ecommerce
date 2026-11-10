<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Product
 */
class ProductResource extends ProductSummaryResource
{
    /**
     * The product page payload, including gallery, variants and the store profile.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'sku' => $this->sku,
            'images' => $this->whenLoaded('images', fn (): array => $this->images
                ->map(fn (ProductImage $image): array => [
                    'url' => Storage::disk('product_images')->url($image->path),
                    'color' => $image->color,
                    'position' => (int) $image->position,
                ])
                ->all()),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'store' => $this->whenLoaded('store', fn (): array => [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'slug' => $this->store->slug,
                'description' => $this->store->description,
                'city' => $this->store->city,
                'logo_url' => $this->store->logo_path === null
                    ? null
                    : Storage::disk('public')->url($this->store->logo_path),
                'accepts_online_payments' => $this->store->acceptsOnlinePayments(),
                'rating' => $this->when(isset($this->store->ratings_count), fn (): array => StoreResource::ratingSummary($this->store)),
            ]),
        ];
    }
}
