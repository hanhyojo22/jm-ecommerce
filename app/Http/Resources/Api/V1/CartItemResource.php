<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin CartItem
 */
class CartItemResource extends JsonResource
{
    /**
     * A cart line, its live availability and the product facts the app needs to render it.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'quantity' => (int) $this->quantity,
            'selected' => (bool) $this->selected,
            'available' => $this->isAvailable(),
            'unit_price' => $this->unitPrice(),
            'line_total' => bcmul($this->unitPrice(), (string) $this->quantity, 2),
            'image_url' => $this->imageUrl(),
            'product' => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'slug' => $this->product->slug,
                'price' => (string) $this->product->price,
                'stock_quantity' => (int) $this->product->stock_quantity,
                'has_variants' => $this->product->variants->isNotEmpty(),
                'store' => [
                    'name' => $this->product->store->name,
                    'slug' => $this->product->store->slug,
                ],
            ],
            'variant' => $this->when(
                $this->relationLoaded('variant') && $this->variant !== null,
                fn (): array => [
                    'id' => $this->variant->id,
                    'label' => $this->variant->label(),
                    'color' => $this->variant->color !== '' ? $this->variant->color : null,
                    'color_hex' => $this->variant->swatch(),
                    'size' => $this->variant->size !== '' ? $this->variant->size : null,
                    'price' => $this->variant->price !== null ? (string) $this->variant->price : null,
                    'stock_quantity' => (int) $this->variant->stock_quantity,
                ],
            ),
        ];
    }

    private function imageUrl(): ?string
    {
        $path = $this->imagePath();

        return $path === null ? null : Storage::disk('product_images')->url($path);
    }
}
