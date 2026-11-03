<?php

namespace App\Actions;

use App\Models\OrderIssue;
use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpenOrderIssue
{
    /**
     * Open the buyer's one support request for a store order.
     */
    public function handle(SellerOrder $sellerOrder, User $buyer, string $type, string $details): OrderIssue
    {
        return DB::transaction(function () use ($sellerOrder, $buyer, $type, $details): OrderIssue {
            $sellerOrder = SellerOrder::query()->with('order')->lockForUpdate()->findOrFail($sellerOrder->id);
            abort_unless($sellerOrder->order->user_id === $buyer->id, 404);

            if ($type === 'cancellation' && ! in_array($sellerOrder->status, ['pending', 'confirmed'], true)) {
                throw ValidationException::withMessages(['type' => 'This order is already being prepared or delivered. Ask for order help instead.']);
            }

            if ($sellerOrder->issues()->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['type' => 'A request for this store order is already open.']);
            }

            return OrderIssue::query()->create([
                'seller_order_id' => $sellerOrder->id,
                'buyer_id' => $buyer->id,
                'type' => $type,
                'details' => $details,
                'status' => 'open',
            ]);
        });
    }
}
