<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductReviewRequest;
use App\Models\OrderItem;
use App\Models\ProductReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductReviewController extends Controller
{
    public function store(StoreProductReviewRequest $request, OrderItem $orderItem): RedirectResponse
    {
        $orderItem = $this->eligibleItem($request, $orderItem);
        $data = $request->validated();

        DB::transaction(function () use ($request, $orderItem, $data): void {
            $review = ProductReview::query()->create([
                'product_id' => $orderItem->product_id,
                'order_item_id' => $orderItem->id,
                'user_id' => $request->user()->id,
                'rating' => $data['rating'],
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? null,
            ]);

            foreach ($request->file('photos', []) as $position => $photo) {
                $review->photos()->create([
                    'path' => $photo->store('product-reviews', 'public'),
                    'position' => $position,
                ]);
            }
        });

        return to_route('orders.show', $orderItem->sellerOrder->order_id)->with('status', 'Your review was published.');
    }

    public function update(StoreProductReviewRequest $request, ProductReview $review): RedirectResponse
    {
        $this->authorize('update', $review);
        $data = $request->validated();

        DB::transaction(function () use ($request, $review, $data): void {
            $review->update([
                'rating' => $data['rating'],
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? null,
            ]);

            foreach ($request->file('photos', []) as $position => $photo) {
                $review->photos()->create([
                    'path' => $photo->store('product-reviews', 'public'),
                    'position' => $review->photos()->count() + $position,
                ]);
            }
        });

        return back()->with('status', 'Your review was updated.');
    }

    public function destroy(Request $request, ProductReview $review): RedirectResponse
    {
        $this->authorize('delete', $review);

        foreach ($review->photos as $photo) {
            Storage::disk('public')->delete($photo->path);
        }

        $review->delete();

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
            && $orderItem->sellerOrder->status === 'delivered'
            && $orderItem->review === null,
            403,
        );

        return $orderItem;
    }
}
