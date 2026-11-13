<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Product
 */
class ProductSummaryResource extends JsonResource
{
    /**
     * The shape used by product lists: enough to render a card without the detail payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'price' => (string) $this->price,
            'compare_at_price' => $this->compare_at_price !== null ? (string) $this->compare_at_price : null,
            'effective_price' => $this->effectivePrice(),
            'has_variants' => (bool) ($this->variants_exists ?? false),
            'requires_variant_selection' => (bool) ($this->variants_exists ?? false),
            'brand' => $this->brand,
            'condition' => $this->condition,
            'free_shipping' => (bool) $this->free_shipping,
            'stock_quantity' => (int) $this->stock_quantity,
            'in_stock' => $this->stock_quantity > 0,
            'thumbnail_url' => $this->thumbnailUrl(),
            'store' => $this->whenLoaded('store', fn (): array => [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'slug' => $this->store->slug,
                'city' => $this->store->city,
                'logo_url' => $this->store->logo_path === null
                    ? null
                    : Storage::disk('public')->url($this->store->logo_path),
            ]),
            'category' => $this->whenLoaded('category', fn (): ?array => $this->category === null ? null : [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'rating' => [
                'average' => round((float) ($this->published_reviews_avg_rating ?? 0), 1),
                'count' => (int) ($this->published_reviews_count ?? 0),
            ],
            'sold_quantity' => (int) ($this->sold_quantity ?? 0),
            'net_quantity_sold' => $this->when(isset($this->net_quantity_sold), fn (): int => (int) $this->net_quantity_sold),
        ];
    }

    /**
     * The lowest price a buyer can actually pay, matching the web's "from" price.
     */
    private function effectivePrice(): string
    {
        $price = (string) $this->price;

        if (
            $this->variants_min_price !== null
            && (! $this->variants_using_base_price_exists || bccomp((string) $this->variants_min_price, $price, 2) < 0)
        ) {
            return bcadd((string) $this->variants_min_price, '0', 2);
        }

        return $price;
    }

    private function thumbnailUrl(): ?string
    {
        $thumbnail = $this->relationLoaded('thumbnail') ? $this->thumbnail : null;

        return $thumbnail === null ? null : Storage::disk('product_images')->url($thumbnail->path);
    }
}
