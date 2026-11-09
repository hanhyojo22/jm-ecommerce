<?php

namespace App\Http\Controllers\Marketplace;

use App\Actions\PublishProductReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductReviewRequest;
use App\Models\OrderItem;
use App\Models\ProductReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductReviewController extends Controller
{
    public function index(Request $request): View
    {
        return view('marketplace.reviews.index', [
            'reviews' => $request->user()->productReviews()
                ->with(['product.store', 'product.thumbnail', 'orderItem.sellerOrder.order:id,number', 'photos', 'response'])
                ->latest()->paginate(10),
            'averageRating' => $request->user()->productReviews()->avg('rating'),
        ]);
    }

    public function store(StoreProductReviewRequest $request, OrderItem $orderItem, PublishProductReview $publish): RedirectResponse
    {
        $orderItem = $this->eligibleItem($request, $orderItem);

        $publish->create(
            $orderItem,
            $request->user(),
            $request->validated(),
            $request->file('photos', []),
        );

        return to_route('orders.show', $orderItem->sellerOrder->order_id)->with('status', 'Your review was published.');
    }

    public function update(StoreProductReviewRequest $request, ProductReview $review, PublishProductReview $publish): RedirectResponse
    {
        $this->authorize('update', $review);

        $publish->update(
            $review,
            $request->validated(),
            $request->file('photos', []),
        );

        return back()->with('status', 'Your review was updated.');
    }

    public function destroy(Request $request, ProductReview $review, PublishProductReview $publish): RedirectResponse
    {
        $this->authorize('delete', $review);
        $publish->delete($review);

        return back()->with('status', 'Your review was deleted.');
    }

    public function report(Request $request, ProductReview $review): RedirectResponse
    {
        abort_unless($review->status === 'published', 404);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:100'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $review->reports()->firstOrCreate(['user_id' => $request->user()->id], $data);

        return back()->with('status', 'Thank you. Your report has been submitted.');
    }

    private function eligibleItem(Request $request, OrderItem $orderItem): OrderItem
    {
        $orderItem->loadMissing('sellerOrder.order', 'review');

        abort_unless(
            $orderItem->product_id !== null
            && $orderItem->sellerOrder->order->user_id === $request->user()->id
            && ! $request->user()->isMemberOfTenant($orderItem->sellerOrder->tenant_id)
            && $orderItem->sellerOrder->status === 'delivered'
            && $orderItem->review === null,
            403,
        );

        return $orderItem;
    }
}
