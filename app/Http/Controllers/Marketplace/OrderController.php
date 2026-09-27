<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,confirmed,processing,shipped,delivered,cancelled'],
        ]);
        $orders = $request->user()->orders()
            ->with(['payment', 'sellerOrders.store', 'sellerOrders.items.product.images'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->whereRaw('LOWER(number) LIKE ?', ['%'.mb_strtolower($search).'%']);
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('marketplace.orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'statusCounts' => $request->user()->orders()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        return view('marketplace.orders.show', ['order' => $order->load('sellerOrders.store', 'sellerOrders.items.product.images', 'payment')]);
    }
}
