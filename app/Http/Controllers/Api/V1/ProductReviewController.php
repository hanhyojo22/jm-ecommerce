<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\PublishProductReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductReviewRequest;
use App\Http\Resources\Api\V1\ProductReviewResource;
use App\Models\OrderItem;
use App\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    public function store(StoreProductReviewRequest $request, OrderItem $orderItem, PublishProductReview $publish): JsonResponse
    {
        $orderItem->loadMissing('sellerOrder.order', 'review');

        abort_unless(
            $orderItem->product_id !== null
            && $orderItem->sellerOrder->order->user_id === $request->user()->id
            && ! $request->user()->isMemberOfTenant($orderItem->sellerOrder->tenant_id)
            && $orderItem->sellerOrder->status === 'delivered'
            && $orderItem->review === null,
            404,
        );

        $review = $publish->create(
            $orderItem,
            $request->user(),
            $request->validated(),
            $request->file('photos', []),
        );

        return $this->payload($review, $request, 201);
    }

    public function update(StoreProductReviewRequest $request, ProductReview $review, PublishProductReview $publish): JsonResponse
    {
        $this->authorize('update', $review);

        $review = $publish->update(
            $review,
            $request->validated(),
            $request->file('photos', []),
        );

        return $this->payload($review, $request);
    }

    public function destroy(Request $request, ProductReview $review, PublishProductReview $publish): JsonResponse
    {
        $this->authorize('delete', $review);

        $publish->delete($review);

        return response()->json(null, 204);
    }

    private function payload(ProductReview $review, Request $request, int $status = 200): JsonResponse
    {
        $review->load(['user:id,name', 'photos', 'responses.user:id,name', 'orderItem']);

        return ProductReviewResource::make($review)->response($request)->setStatusCode($status);
    }
}
