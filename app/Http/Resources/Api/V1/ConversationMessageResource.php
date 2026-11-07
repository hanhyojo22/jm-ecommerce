<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ConversationMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin ConversationMessage
 */
class ConversationMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sender_id' => $this->sender_id,
            'sender_name' => $this->whenLoaded('sender', fn (): string => $this->sender->name),
            'is_mine' => $request->user()?->id === $this->sender_id,
            'type' => $this->type,
            'body' => $this->body,
            'product_id' => $this->product_id,
            'seller_order_id' => $this->seller_order_id,
            'context_snapshot' => $this->context_snapshot === null ? null : [
                ...$this->context_snapshot,
                'image_url' => filled($this->context_snapshot['image_path'] ?? null)
                    ? Storage::disk('product_images')->url($this->context_snapshot['image_path']) : null,
            ],
            'client_message_id' => $this->client_message_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'receipt' => $this->when($this->receipt !== null, fn (): string => $this->receipt),
            'attachments' => MessageAttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
