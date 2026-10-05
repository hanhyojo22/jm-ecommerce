<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\SellerOrder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function startForProduct(Request $request, Product $product): RedirectResponse
    {
        abort_unless(
            $product->status === 'active' && $product->store->status === 'active' && $product->store->tenant->status === 'active',
            404,
        );

        $conversation = $this->findOrCreate(
            ['buyer_id' => $request->user()->id, 'product_id' => $product->id],
            ['store_id' => $product->store_id],
        );

        return to_route('messages.show', $conversation);
    }

    public function startForSellerOrder(Request $request, SellerOrder $sellerOrder): RedirectResponse
    {
        $sellerOrder->loadMissing('order', 'store');
        abort_unless($sellerOrder->order->user_id === $request->user()->id, 404);

        $conversation = $this->findOrCreate(
            ['buyer_id' => $request->user()->id, 'seller_order_id' => $sellerOrder->id],
            ['store_id' => $sellerOrder->store_id],
        );

        return to_route('messages.show', $conversation);
    }

    /**
     * @param  array<string, string>  $identity
     * @param  array<string, string>  $attributes
     */
    private function findOrCreate(array $identity, array $attributes): Conversation
    {
        try {
            return Conversation::create([...$identity, ...$attributes]);
        } catch (QueryException) {
            return Conversation::query()->where($identity)->firstOrFail();
        }
    }
}
