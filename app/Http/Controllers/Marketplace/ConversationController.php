<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\BlockedUser;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\TenantMember;
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
        abort_if($request->user()->isMemberOfTenant($product->tenant_id), 403);

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
        abort_if($request->user()->isMemberOfTenant($sellerOrder->tenant_id), 403);

        $conversation = $this->findOrCreate(
            ['buyer_id' => $request->user()->id, 'seller_order_id' => $sellerOrder->id],
            ['store_id' => $sellerOrder->store_id],
        );

        return to_route('messages.show', $conversation);
    }

    public function block(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('view', $conversation);
        $sellerId = TenantMember::query()
            ->where('tenant_id', $conversation->store->tenant_id)
            ->where('role', 'owner')
            ->value('user_id');
        abort_if($sellerId === null, 409);

        BlockedUser::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'blocked_user_id' => $conversation->buyer_id === $request->user()->id ? $sellerId : $conversation->buyer_id,
        ]);

        return to_route('messages.index')->with('status', 'This conversation has been blocked.');
    }

    public function report(Request $request, Conversation $conversation, ConversationMessage $message): RedirectResponse
    {
        $this->authorize('view', $conversation);
        abort_unless($message->conversation_id === $conversation->id && $message->sender_id !== $request->user()->id, 404);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:100'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $message->reports()->firstOrCreate(['reporter_id' => $request->user()->id], $data);

        return back()->with('status', 'Your report has been submitted.');
    }

    /**
     * @param  array<string, string>  $identity
     * @param  array<string, string>  $attributes
     */
    private function findOrCreate(array $identity, array $attributes): Conversation
    {
        return Conversation::query()->createOrFirst($identity, $attributes);
    }
}
