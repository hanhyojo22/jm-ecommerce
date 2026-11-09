<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\PublishProductReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductReviewRequest;
use App\Http\Resources\Api\V1\ProductReviewResource;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Queries\CatalogQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    public function eligibility(Request $request, Product $product): JsonResponse
    {
        abort_unless(CatalogQuery::publishedProducts()->whereKey($product->id)->exists(), 404);

        $item = OrderItem::query()
            ->where('product_id', $product->id)
            ->whereDoesntHave('review')
            ->whereHas('sellerOrder', fn (Builder $query) => $query
                ->where('status', 'delivered')
                ->whereHas('order', fn (Builder $order) => $order->where('user_id', $request->user()->id)))
            ->latest()
            ->first();

        return response()->json(['data' => [
            'order_item_id' => $request->user()->isMemberOfTenant($product->tenant_id) ? null : $item?->id,
        ]]);
    }

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
