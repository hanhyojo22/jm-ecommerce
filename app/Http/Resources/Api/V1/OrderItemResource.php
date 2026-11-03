<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'sku' => $this->sku,
            'variant_label' => $this->variant_label,
            'unit_price' => (string) $this->unit_price,
            'quantity' => (int) $this->quantity,
            'line_total' => (string) $this->line_total,
            'image_url' => $this->image_path === null ? null : Storage::disk('product_images')->url($this->image_path),
            'reviewed' => $this->whenLoaded('review', fn (): bool => $this->review !== null),
        ];
    }
}
