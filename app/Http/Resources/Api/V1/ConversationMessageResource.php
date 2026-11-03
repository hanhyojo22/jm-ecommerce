<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ConversationMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'client_message_id' => $this->client_message_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'attachments' => MessageAttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
