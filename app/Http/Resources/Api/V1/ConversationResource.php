<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $latestMessage = $this->relationLoaded('messages') ? $this->messages->first() : null;

        return [
            'id' => $this->id,
            'latest_message_at' => $this->latest_message_at?->toIso8601String(),
            'unread_count' => (int) ($this->unread_messages_count ?? 0),
            'store' => $this->whenLoaded('store', fn (): array => [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'slug' => $this->store->slug,
                'logo_url' => $this->store->logo_path === null
                    ? null
                    : Storage::disk('public')->url($this->store->logo_path),
            ]),
            'product' => $this->whenLoaded('product', fn (): ?array => $this->product === null ? null : [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'slug' => $this->product->slug,
                'thumbnail_url' => $this->product->thumbnail === null
                    ? null
                    : Storage::disk('product_images')->url($this->product->thumbnail->path),
            ]),
            'seller_order' => $this->whenLoaded('sellerOrder', fn (): ?array => $this->sellerOrder === null ? null : [
                'id' => $this->sellerOrder->id,
                'order_id' => $this->sellerOrder->order_id,
                'status' => $this->sellerOrder->status,
                'short_number' => $this->sellerOrder->order === null
                    ? null
                    : Order::shortNumber($this->sellerOrder->order->number),
            ]),
            'latest_message' => $latestMessage instanceof ConversationMessage
                ? ConversationMessageResource::make($latestMessage)->resolve($request)
                : null,
        ];
    }
}
