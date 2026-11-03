<?php

namespace App\Http\Controllers\Marketplace;

use App\Actions\OpenOrderIssue;
use App\Http\Controllers\Controller;
use App\Models\SellerOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderIssueController extends Controller
{
    public function store(Request $request, SellerOrder $sellerOrder, OpenOrderIssue $openIssue): RedirectResponse
    {
        $this->authorize('openIssue', $sellerOrder);

        $data = $request->validate([
            'type' => ['required', 'in:cancellation,delivery,other'],
            'details' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $openIssue->handle($sellerOrder, $request->user(), $data['type'], $data['details']);

        return to_route('orders.show', $sellerOrder->order_id)->with('status', 'Your request was sent to JMMart support.');
    }
}
