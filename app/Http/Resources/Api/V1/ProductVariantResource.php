<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label(),
            'color' => $this->color !== '' ? $this->color : null,
            'color_hex' => $this->swatch(),
            'size' => $this->size !== '' ? $this->size : null,
            'sku' => $this->sku,
            'price' => $this->price !== null ? (string) $this->price : null,
            'stock_quantity' => (int) $this->stock_quantity,
            'in_stock' => $this->stock_quantity > 0,
        ];
    }
}
