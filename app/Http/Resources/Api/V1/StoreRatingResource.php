<?php

namespace App\Http\Resources\Api\V1;

use App\Models\StoreRating;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StoreRating
 */
class StoreRatingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'author' => $this->whenLoaded('user', fn (): string => $this->user->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
