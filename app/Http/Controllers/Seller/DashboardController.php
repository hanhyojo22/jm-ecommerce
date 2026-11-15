<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\PlatformFeeStatement;
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
            $productMetrics = $tenant->products()
                ->toBase()
                ->selectRaw("count(*) as total_products, sum(case when stock_quantity = 0 then 1 else 0 end) as out_of_stock_products, sum(case when status = 'active' then 1 else 0 end) as active_products")
                ->first();
            $orderMetrics = $member->sellerOrders()
                ->toBase()
                ->selectRaw("count(*) as total_orders, sum(case when status = 'pending' then 1 else 0 end) as pending_orders, sum(case when status != 'cancelled' then total else 0 end) as sales_total")
                ->first();
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

            return response()->view('seller.dashboard', [
                'store' => $store,
                'needsFeeTerms' => ! $tenant->hasAcceptedCurrentFeeTerms(),
                'unpaidFeeStatement' => PlatformFeeStatement::query()->where('tenant_id', $tenant->id)->where('status', 'unpaid')->where('amount_due', '>', 0)->orderBy('due_at')->first(),
                'totalProducts' => (int) $productMetrics->total_products,
                'activeProducts' => (int) $productMetrics->active_products,
                'pendingOrderCount' => (int) $orderMetrics->pending_orders,
                'totalOrders' => (int) $orderMetrics->total_orders,
                'salesTotal' => $orderMetrics->sales_total ?? 0,
                'salesHistory' => $salesHistory,
                'customerCount' => $customerCount,
                'outOfStockCount' => (int) $productMetrics->out_of_stock_products,
                'recentOrders' => $member->sellerOrders()
                    ->with('order')
                    ->withCount('items')
                    ->latest()
                    ->limit(5)
                    ->get(),
                'recentProducts' => $tenant->products()
                    ->with('images')
                    ->latest('updated_at')
                    ->limit(4)
                    ->get(),
                'canManageProducts' => $canManageProducts,
                'canManageOrders' => $canManageOrders,
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
