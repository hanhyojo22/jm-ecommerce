<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,confirmed,processing,shipped,delivered,cancelled'],
            'tab' => ['nullable', 'in:all,awaiting_confirmation,to_dispatch,to_receive,to_review,returns'],
        ]);
        $search = trim($filters['search'] ?? '');
        $searchTerm = '%'.mb_strtolower($search).'%';
        $tab = $filters['tab'] ?? 'all';
        $ownedTenantIds = $request->user()->tenantMemberships()
            ->whereIn('role', ['owner', 'manager', 'staff'])
            ->pluck('tenant_id')->all();
        $tabs = [
            'awaiting_confirmation' => 'Awaiting Confirmation',
            'to_dispatch' => 'To Dispatch',
            'to_receive' => 'To Receive',
            'to_review' => 'To Review',
            'returns' => 'Returns',
        ];

        $baseQuery = SellerOrder::query()
            ->whereHas('order', fn (Builder $query) => $query->where('user_id', $request->user()->id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->whereHas('order', fn (Builder $orderQuery) => $orderQuery->where('status', $status)))
            ->when($search !== '', function (Builder $query) use ($searchTerm): void {
                $query->where(function (Builder $searchQuery) use ($searchTerm): void {
                    $searchQuery->whereHas('order', fn (Builder $orderQuery) => $orderQuery->whereRaw('LOWER(number) LIKE ?', [$searchTerm]))
                        ->orWhereHas('store', fn (Builder $storeQuery) => $storeQuery->whereRaw('LOWER(name) LIKE ?', [$searchTerm]))
                        ->orWhereHas('items', fn (Builder $itemQuery) => $itemQuery->whereRaw('LOWER(product_name) LIKE ?', [$searchTerm]));
                });
            });

        $tabCounts = [];
        foreach (array_keys($tabs) as $key) {
            $tabCounts[$key] = $this->inTab(clone $baseQuery, $key, $ownedTenantIds)->count();
        }

        $sellerOrders = $this->inTab(clone $baseQuery, $tab, $ownedTenantIds)
            ->with([
                'order' => fn ($query) => $query->select('id', 'user_id', 'number', 'total', 'created_at')->withCount('sellerOrders'),
                'order.payment:id,order_id,method,status',
                'store:id,name,slug,logo_path',
                'items.product:id,store_id,slug',
                'items.product.thumbnail',
                'items.product.store:id,slug',
                'items.review:id,order_item_id',
                'issues' => fn ($query) => $query->latest(),
            ])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $reviewableItemIds = [];
        foreach ($sellerOrders as $sellerOrder) {
            if ($sellerOrder->status === 'delivered' && ! in_array($sellerOrder->tenant_id, $ownedTenantIds, true)) {
                $reviewableItemIds[$sellerOrder->id] = $sellerOrder->items
                    ->first(fn (OrderItem $item): bool => $item->product_id !== null && $item->review === null)?->id;
            }
        }

        return view('marketplace.orders.index', [
            'sellerOrders' => $sellerOrders,
            'filters' => $filters,
            'tab' => $tab,
            'tabs' => $tabs,
            'tabCounts' => $tabCounts,
            'reviewableItemIds' => $reviewableItemIds,
        ]);
    }

    /**
     * @param  array<int, string>  $ownedTenantIds
     */
    private function inTab(Builder $query, string $tab, array $ownedTenantIds): Builder
    {
        return match ($tab) {
            'awaiting_confirmation' => $query->where('status', 'pending'),
            'to_dispatch' => $query->whereIn('status', ['confirmed', 'processing']),
            'to_receive' => $query->where('status', 'shipped'),
            'to_review' => $query->where('status', 'delivered')
                ->whereNotIn('tenant_id', $ownedTenantIds)
                ->whereHas('items', fn (Builder $itemQuery) => $itemQuery->whereNotNull('product_id')->whereDoesntHave('review')),
            'returns' => $query->whereHas('issues', fn (Builder $issueQuery) => $issueQuery->where('type', 'return_refund')),
            default => $query,
        };
    }

    public function show(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return view('marketplace.orders.show', ['order' => $order->load('sellerOrders.store', 'sellerOrders.issues', 'sellerOrders.items.product.images', 'sellerOrders.items.product.store', 'sellerOrders.items.review', 'payment')]);
    }
}
