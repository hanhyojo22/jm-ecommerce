<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\ProductReview;
use App\Models\ProductReviewResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveSellerReviewResponse
{
    public function handle(ProductReview $review, User $seller, string $body, ?string $ipAddress): ProductReviewResponse
    {
        return DB::transaction(function () use ($review, $seller, $body, $ipAddress): ProductReviewResponse {
            ProductReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();
            $response = $review->response()->updateOrCreate(
                ['parent_response_id' => null, 'author_type' => 'seller'],
                ['user_id' => $seller->id, 'body' => $body],
            );

            AuditLog::query()->create([
                'actor_user_id' => $seller->id,
                'action' => 'product_review.response_saved',
                'entity_type' => ProductReview::class,
                'entity_id' => $review->id,
                'new_values' => ['body' => $body],
                'ip_address' => $ipAddress,
            ]);

            return $response;
        });
    }
}
