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
        $search = trim($filters['search'] ?? '');
        $searchTerm = '%'.mb_strtolower($search).'%';
        $orders = $request->user()->orders()
            ->with([
                'payment',
                'sellerOrders.store',
                'sellerOrders.items.product:id,store_id,slug',
                'sellerOrders.items.product.thumbnail',
                'sellerOrders.items.product.store:id,slug',
            ])
            ->when($search !== '', function ($query) use ($searchTerm): void {
                $query->where(function ($orderQuery) use ($searchTerm): void {
                    $orderQuery->whereRaw('LOWER(number) LIKE ?', [$searchTerm])
                        ->orWhereHas('sellerOrders.store', fn ($storeQuery) => $storeQuery->whereRaw('LOWER(name) LIKE ?', [$searchTerm]))
                        ->orWhereHas('sellerOrders.items', fn ($itemQuery) => $itemQuery->whereRaw('LOWER(product_name) LIKE ?', [$searchTerm]));
                });
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

        return view('marketplace.orders.show', ['order' => $order->load('sellerOrders.store', 'sellerOrders.items.product.images', 'sellerOrders.items.product.store', 'sellerOrders.items.review', 'payment')]);
    }
}
