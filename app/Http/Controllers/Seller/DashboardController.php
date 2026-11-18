<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        try {
            $member = $request->user()->sellerMembership();

            if ($member === null) {
                return to_route('home');
            }

            $tenant = $member->tenant;
            // Head office sees the whole company; branch staff see their own branch.
            $store = $member->store ?? $tenant->store;
            $branch = $member->isHeadOffice() ? null : $member->store;
            $productMetrics = $tenant->products()
                ->toBase()
                ->selectRaw("count(*) as total_products, sum(case when stock_quantity = 0 then 1 else 0 end) as out_of_stock_products, sum(case when status = 'active' then 1 else 0 end) as active_products")
                ->first();
            $orderMetrics = $member->sellerOrders()
                ->toBase()
                ->selectRaw("count(*) as total_orders, sum(case when status = 'pending' then 1 else 0 end) as pending_orders, sum(case when status = 'confirmed' then 1 else 0 end) as confirmed_orders, sum(case when status = 'processing' then 1 else 0 end) as processing_orders, sum(case when status = 'shipped' then 1 else 0 end) as shipped_orders, sum(case when status != 'cancelled' then total else 0 end) as sales_total")
                ->first();
            // A prescription sent back for a clearer photo waits on the customer, not the pharmacist.
            $waitingForPharmacist = fn (Builder $query): Builder => $query
                ->where('status', 'pending')
                ->whereHas('order.prescription', fn (Builder $prescription): Builder => $prescription->where('status', 'pending'));
            $prescriptionsWaitingCount = $member->sellerOrders()->tap($waitingForPharmacist)->count();
            $customerCount = $member->sellerOrders()
                ->join('orders', 'orders.id', '=', 'seller_orders.order_id')
                ->where('seller_orders.status', '!=', 'cancelled')
                ->distinct()
                ->count('orders.user_id');
            $salesByDay = $member->sellerOrders()
                ->where('status', '!=', 'cancelled')
                ->where('created_at', '>=', now()->subDays(29)->startOfDay())
                ->toBase()
                ->selectRaw('date(created_at) as day, sum(total) as amount')
                ->groupByRaw('date(created_at)')
                ->pluck('amount', 'day');
            $salesHistory = collect(range(29, 0))
                ->map(fn (int $daysAgo): array => [
                    'date' => now()->subDays($daysAgo)->toDateString(),
                    'amount' => $salesByDay->get(now()->subDays($daysAgo)->toDateString(), 0),
                ]);
            $canManageOrders = in_array($member->role, ['owner', 'manager'], true);
            $canManageProducts = $canManageOrders && $member->isHeadOffice();
            $storeSettingsUrl = $canManageProducts ? route('seller.store-settings.edit') : null;
            $branches = $canManageProducts ? $tenant->stores()
                ->orderBy('name')
                ->withCount([
                    'sellerOrders as pending_orders_count' => fn (Builder $query): Builder => $query->where('status', 'pending'),
                    'sellerOrders as prescriptions_waiting_count' => $waitingForPharmacist,
                ])
                ->get() : collect();

            return response()->view('seller.dashboard', [
                'store' => $store,
                'totalProducts' => (int) $productMetrics->total_products,
                'activeProducts' => (int) $productMetrics->active_products,
                'pendingOrderCount' => (int) $orderMetrics->pending_orders,
                'orderQueue' => [
                    ['count' => (int) $orderMetrics->pending_orders, 'label' => 'To accept', 'href' => route('seller.orders.index', ['status' => 'pending']), 'urgent' => true],
                    ['count' => (int) $orderMetrics->confirmed_orders, 'label' => 'Accepted', 'href' => route('seller.orders.index', ['status' => 'confirmed']), 'urgent' => false],
                    ['count' => (int) $orderMetrics->processing_orders, 'label' => 'Preparing', 'href' => route('seller.orders.index', ['status' => 'processing']), 'urgent' => false],
                    ['count' => (int) $orderMetrics->shipped_orders, 'label' => 'Ready or out for delivery', 'href' => route('seller.orders.index', ['status' => 'shipped']), 'urgent' => false],
                ],
                'member' => $member,
                'prescriptionsWaitingCount' => $prescriptionsWaitingCount,
                'prescriptionQueue' => $member->canReviewPrescriptions() ? $member->sellerOrders()
                    ->tap($waitingForPharmacist)
                    ->with(['order.prescription', 'store'])
                    ->withCount('items')
                    ->oldest()
                    ->limit(5)
                    ->get() : collect(),
                // Only worth a table once there is more than one branch to compare.
                'branches' => $branches->count() > 1 ? $branches : collect(),
                'totalOrders' => (int) $orderMetrics->total_orders,
                'salesTotal' => $orderMetrics->sales_total ?? 0,
                'salesHistory' => $salesHistory,
                'customerCount' => $customerCount,
                'outOfStockCount' => $branch === null ? (int) $productMetrics->out_of_stock_products : $tenant->products()
                    ->whereDoesntHave('branchStocks', fn ($query) => $query->where('store_id', $branch->id)->where('quantity', '>', 0))->count(),
                'recentOrders' => $member->sellerOrders()
                    ->with('order')
                    ->withCount('items')
                    ->latest()
                    ->limit(5)
                    ->get(),
                'recentProducts' => $tenant->products()
                    ->with('images')
                    ->when($branch, fn ($query) => $query->withSum(['branchStocks as branch_quantity' => fn ($stock) => $stock->where('store_id', $branch->id)], 'quantity'))
                    ->latest('updated_at')
                    ->limit(4)
                    ->get(),
                'canManageProducts' => $canManageProducts,
                'canManageOrders' => $canManageOrders,
                'isHeadOffice' => $member->isHeadOffice(),
                'setupItems' => [
                    ['label' => 'Store profile', 'complete' => filled($store->description), 'href' => $storeSettingsUrl, 'action' => 'Edit details'],
                    ['label' => 'Store logo', 'complete' => filled($store->logo_path), 'href' => $storeSettingsUrl, 'action' => 'Upload logo'],
                    ['label' => 'Contact information', 'complete' => filled($store->phone) && filled($store->address_line) && filled($store->city), 'href' => $storeSettingsUrl, 'action' => 'Edit contact'],
                    ['label' => 'First product', 'complete' => (int) $productMetrics->total_products > 0, 'href' => $canManageProducts ? route('seller.products.create') : null, 'action' => 'Add product'],
                ],
            ]);
        } catch (QueryException $exception) {
            report($exception);

            return response()->view('seller.dashboard-unavailable', [], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
