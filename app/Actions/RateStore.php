<?php

namespace App\Actions;

use App\Models\SellerOrder;
use App\Models\StoreRating;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RateStore
{
    /**
     * Record the buyer's one rating of the store for a delivered store order.
     *
     * @param  array{rating: int, comment?: string|null}  $data
     *
     * @throws ValidationException when the order is not delivered, is the buyer's own store, or is already rated
     */
    public function handle(User $buyer, SellerOrder $sellerOrder, array $data): StoreRating
    {
        return DB::transaction(function () use ($buyer, $sellerOrder, $data): StoreRating {
            $sellerOrder = SellerOrder::query()->lockForUpdate()->findOrFail($sellerOrder->id);

            if ($sellerOrder->status !== 'delivered') {
                throw ValidationException::withMessages(['rating' => 'You can rate the store once this order is delivered.']);
            }

            if ($buyer->isMemberOfTenant($sellerOrder->tenant_id)) {
                throw ValidationException::withMessages(['rating' => 'You can’t rate your own store.']);
            }

            if ($sellerOrder->storeRating()->exists()) {
                throw ValidationException::withMessages(['rating' => 'You already rated this store for this order.']);
            }

            $comment = trim((string) ($data['comment'] ?? ''));

            return StoreRating::query()->create([
                'store_id' => $sellerOrder->store_id,
                'seller_order_id' => $sellerOrder->id,
                'user_id' => $buyer->id,
                'rating' => $data['rating'],
                'comment' => $comment === '' ? null : $comment,
            ]);
        });
    }
}
