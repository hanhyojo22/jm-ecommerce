<?php

namespace App\Actions;

use App\Models\Conversation;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConversationContext
{
    /** @return array{product_id?: string, seller_order_id?: string, context_snapshot?: array<string, mixed>} */
    public function forMessage(Conversation $conversation, User $sender, ?string $productId, ?string $sellerOrderId, bool $preserveImage = true): array
    {
        if ($productId !== null && $sellerOrderId !== null) {
            throw ValidationException::withMessages(['product_id' => 'Choose one context only.']);
        }
        if ($productId === null && $sellerOrderId === null) {
            return [];
        }
        if ($sender->id !== $conversation->buyer_id) {
            throw ValidationException::withMessages(['product_id' => 'Only the buyer can start an inquiry.']);
        }
        if ($productId !== null) {
            $product = Product::query()->with(['store.tenant', 'thumbnail'])->findOrFail($productId);
            abort_unless($product->store_id === $conversation->store_id && $product->status === 'active' && $product->store->status === 'active' && $product->store->tenant->status === 'active', 404);

            $imagePath = $product->thumbnail?->path;
            if ($preserveImage && $imagePath !== null && Storage::disk('product_images')->exists($imagePath)) {
                $preservedPath = 'chat-snapshots/'.Str::ulid().'.'.pathinfo($imagePath, PATHINFO_EXTENSION);
                Storage::disk('product_images')->copy($imagePath, $preservedPath);
                $imagePath = $preservedPath;
            }

            return ['product_id' => $product->id, 'context_snapshot' => [
                'type' => 'product', 'title' => $product->name, 'price' => $product->price,
                'image_path' => $imagePath, 'slug' => $product->slug, 'store_slug' => $product->store->slug,
            ]];
        }
        $order = SellerOrder::query()->with(['order.payment', 'store', 'items.product' => fn ($query) => $query
            ->withSum(['orderItems as sold_quantity' => fn (Builder $items) => $items
                ->whereHas('sellerOrder', fn (Builder $sellerOrder) => $sellerOrder->where('status', '!=', 'cancelled'))], 'quantity')
            ->withCount(['reviews as published_reviews_count' => fn (Builder $reviews) => $reviews->published()])
            ->withAvg(['reviews as published_reviews_avg_rating' => fn (Builder $reviews) => $reviews->published()], 'rating'),
        ])->findOrFail($sellerOrderId);
        abort_unless($order->store_id === $conversation->store_id && $order->order->user_id === $sender->id, 404);
        [$whole, $fraction] = array_pad(explode('.', (string) $order->total, 2), 2, '');

        return ['seller_order_id' => $order->id, 'context_snapshot' => [
            'type' => 'order', 'number' => $order->order->number, 'total' => $whole.'.'.str_pad($fraction, 2, '0'),
            'status' => $order->status, 'order_id' => $order->order_id,
            'payment_method' => $order->order->payment?->method, 'city' => $order->store->city,
            'item_count' => (int) $order->items->sum('quantity'),
            'image_path' => $order->items->firstWhere('image_path', '!=', null)?->image_path,
            'items' => $order->items->map(fn ($item): array => [
                'name' => $item->product_name, 'variant' => $item->variant_label,
                'quantity' => (int) $item->quantity, 'image_path' => $item->image_path,
                'rating' => round((float) ($item->product?->published_reviews_avg_rating ?? 0), 1),
                'rating_count' => (int) ($item->product?->published_reviews_count ?? 0),
                'sold' => (int) ($item->product?->sold_quantity ?? 0),
                'free_shipping' => (bool) $item->product?->free_shipping,
            ])->all(),
        ]];
    }
}
