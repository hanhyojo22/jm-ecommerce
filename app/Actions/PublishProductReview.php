<?php

namespace App\Actions;

use App\Models\OrderItem;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PublishProductReview
{
    public function __construct(private StorePublicImage $storePublicImage) {}

    /**
     * Publish one review with its photos, leaving nothing behind if a write fails.
     *
     * @param  array{rating: int, title?: ?string, body?: ?string}  $data
     * @param  array<int, UploadedFile>  $photos
     */
    public function create(OrderItem $orderItem, User $user, array $data, array $photos = []): ProductReview
    {
        $photoPaths = $this->storePhotos($photos);

        try {
            return DB::transaction(function () use ($orderItem, $user, $data, $photoPaths): ProductReview {
                $review = ProductReview::query()->create([
                    'product_id' => $orderItem->product_id,
                    'order_item_id' => $orderItem->id,
                    'user_id' => $user->id,
                    'rating' => $data['rating'],
                    'title' => $data['title'] ?? null,
                    'body' => $data['body'] ?? null,
                ]);

                foreach ($photoPaths as $position => $path) {
                    $review->photos()->create(['path' => $path, 'position' => $position]);
                }

                return $review;
            });
        } catch (Throwable $exception) {
            $this->deletePhotos($photoPaths);

            throw $exception;
        }
    }

    /**
     * Change a review's words and append any new photos.
     *
     * @param  array{rating: int, title?: ?string, body?: ?string}  $data
     * @param  array<int, UploadedFile>  $photos
     */
    public function update(ProductReview $review, array $data, array $photos = []): ProductReview
    {
        $photoPaths = $this->storePhotos($photos);

        try {
            return DB::transaction(function () use ($review, $data, $photoPaths): ProductReview {
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

                return $review;
            });
        } catch (Throwable $exception) {
            $this->deletePhotos($photoPaths);

            throw $exception;
        }
    }

    public function delete(ProductReview $review): void
    {
        $photoPaths = DB::transaction(function () use ($review): array {
            $review = ProductReview::query()->lockForUpdate()->findOrFail($review->id);
            $photoPaths = $review->photos()->pluck('path')->all();
            $review->delete();

            return $photoPaths;
        });

        $this->deletePhotos($photoPaths);
    }

    /**
     * @param  array<int, UploadedFile>  $photos
     * @return array<int, string>
     */
    private function storePhotos(array $photos): array
    {
        $paths = [];

        try {
            foreach ($photos as $photo) {
                $path = $this->storePublicImage->handle($photo, 'product-reviews', 'public');
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
