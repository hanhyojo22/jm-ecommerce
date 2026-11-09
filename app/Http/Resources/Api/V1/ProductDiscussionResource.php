<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ProductDiscussion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductDiscussion
 */
class ProductDiscussionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'root_id' => $this->root_id ?? $this->id,
            'author' => $this->author_type === 'seller' ? $this->product->store->name : $this->user->name,
            'author_type' => $this->author_type,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'replies' => $this->whenLoaded('replies', fn () => self::collection($this->replies)),
        ];
    }
}
