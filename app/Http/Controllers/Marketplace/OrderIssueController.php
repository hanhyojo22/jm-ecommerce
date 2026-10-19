<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\OrderIssue;
use App\Models\SellerOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderIssueController extends Controller
{
    public function store(Request $request, SellerOrder $sellerOrder): RedirectResponse
    {
        abort_unless($sellerOrder->order()->where('user_id', $request->user()->id)->exists(), 404);

        $data = $request->validate([
            'type' => ['required', 'in:cancellation,delivery,other'],
            'details' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $sellerOrder, $data): void {
            $sellerOrder = SellerOrder::query()->with('order')->lockForUpdate()->findOrFail($sellerOrder->id);
            abort_unless($sellerOrder->order->user_id === $request->user()->id, 404);

            if ($data['type'] === 'cancellation' && ! in_array($sellerOrder->status, ['pending', 'confirmed'], true)) {
                throw ValidationException::withMessages(['type' => 'This order is already being prepared or delivered. Ask for order help instead.']);
            }

            if ($sellerOrder->issues()->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['type' => 'A request for this store order is already open.']);
            }

            OrderIssue::query()->create([
                'seller_order_id' => $sellerOrder->id,
                'buyer_id' => $request->user()->id,
                'type' => $data['type'],
                'details' => $data['details'],
            ]);
        });

        return to_route('orders.show', $sellerOrder->order_id)->with('status', 'Your request was sent to JMMart support.');
    }
}
