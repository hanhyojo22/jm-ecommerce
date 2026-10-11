<?php

namespace App\Http\Controllers\Marketplace;

use App\Actions\StorePublicImage;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductReviewRequest;
use App\Models\OrderItem;
use App\Models\ProductReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ProductReviewController extends Controller
{
    public function index(Request $request): View
    {
        return view('marketplace.reviews.index', [
            'reviews' => $request->user()->productReviews()
                ->with(['product.store', 'orderItem.sellerOrder'])
                ->latest()->paginate(10),
        ]);
    }

    public function store(StoreProductReviewRequest $request, OrderItem $orderItem, StorePublicImage $storePublicImage): RedirectResponse
    {
        $orderItem = $this->eligibleItem($request, $orderItem);
        $data = $request->validated();
        $photoPaths = $this->storePhotos($request->file('photos', []), $storePublicImage);

        try {
            DB::transaction(function () use ($request, $orderItem, $data, $photoPaths): void {
                $review = ProductReview::query()->create([
                    'product_id' => $orderItem->product_id,
                    'order_item_id' => $orderItem->id,
                    'user_id' => $request->user()->id,
                    'rating' => $data['rating'],
                    'title' => $data['title'] ?? null,
                    'body' => $data['body'] ?? null,
                ]);

                foreach ($photoPaths as $position => $path) {
                    $review->photos()->create(['path' => $path, 'position' => $position]);
                }
            });
        } catch (Throwable $exception) {
            $this->deletePhotos($photoPaths);

            throw $exception;
        }

        return to_route('orders.show', $orderItem->sellerOrder->order_id)->with('status', 'Your review was published.');
    }

    public function update(StoreProductReviewRequest $request, ProductReview $review, StorePublicImage $storePublicImage): RedirectResponse
    {
        $this->authorize('update', $review);
        $data = $request->validated();
        $photoPaths = $this->storePhotos($request->file('photos', []), $storePublicImage);

        try {
            DB::transaction(function () use ($review, $data, $photoPaths): void {
                $review = ProductReview::query()->lockForUpdate()->findOrFail($review->id);
                $nextPosition = $review->photos()->count();
                $review->update([
                    'rating' => $data['rating'],
                    'title' => $data['title'] ?? null,
                    'body' => $data['body'] ?? null,
                ]);

                foreach ($photoPaths as $position => $path) {
                    $review->photos()->create(['path' => $path, 'position' => $nextPosition + $position]);
                }
            });
        } catch (Throwable $exception) {
            $this->deletePhotos($photoPaths);

            throw $exception;
        }

        return back()->with('status', 'Your review was updated.');
    }

    public function destroy(Request $request, ProductReview $review): RedirectResponse
    {
        $this->authorize('delete', $review);
        $photoPaths = DB::transaction(function () use ($review): array {
            $review = ProductReview::query()->lockForUpdate()->findOrFail($review->id);
            $photoPaths = $review->photos()->pluck('path')->all();
            $review->delete();

            return $photoPaths;
        });
        $this->deletePhotos($photoPaths);

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

    /**
     * @param  array<int, UploadedFile>  $photos
     * @return array<int, string>
     */
    private function storePhotos(array $photos, StorePublicImage $storePublicImage): array
    {
        $paths = [];

        try {
            foreach ($photos as $photo) {
                $path = $storePublicImage->handle($photo, 'product-reviews', 'public');
                if (! is_string($path) || $path === '') {
                    throw new RuntimeException('Review photo storage failed.');
                }

                $paths[] = $path;
            }
        } catch (Throwable $exception) {
            $this->deletePhotos($paths);

            throw $exception;
        }

        return $paths;
    }

    /** @param  array<int, string>  $paths */
    private function deletePhotos(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                Storage::disk('public')->delete($path);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
