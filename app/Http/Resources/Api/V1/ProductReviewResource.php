<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ProductReview;
use App\Models\ProductReviewPhoto;
use App\Models\ProductReviewResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin ProductReview
 */
class ProductReviewResource extends JsonResource
{
    /**
     * A published review and the seller's public reply.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => (int) $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'author' => $this->whenLoaded('user', fn (): string => $this->user->name),
            'verified_purchase' => $this->whenLoaded('orderItem', fn (): bool => $this->orderItem !== null),
            'photos' => $this->whenLoaded('photos', fn (): array => $this->photos
                ->map(fn (ProductReviewPhoto $photo): string => Storage::disk('public')->url($photo->path))
                ->all()),
            'responses' => $this->whenLoaded('responses', fn (): array => $this->responses
                ->map(fn (ProductReviewResponse $response): array => [
                    'author' => $response->user?->name,
                    'author_type' => $response->author_type,
                    'body' => $response->body,
                    'created_at' => $response->created_at?->toIso8601String(),
                ])
                ->all()),
        ];
    }
}
