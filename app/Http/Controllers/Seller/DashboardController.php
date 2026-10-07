<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $tenant = $request->user()->sellerTenant();

        if ($tenant === null) {
            return to_route('seller-application.create');
        }

        $store = $tenant->store;
        $productMetrics = $store->products()
            ->toBase()
            ->selectRaw('count(*) as total_products, sum(case when stock_quantity = 0 then 1 else 0 end) as out_of_stock_products')
            ->first();
        $orderMetrics = $store->sellerOrders()
            ->toBase()
            ->selectRaw("count(*) as total_orders, sum(case when status = 'pending' then 1 else 0 end) as pending_orders, sum(case when status != 'cancelled' then total else 0 end) as sales_total")
            ->first();
        $customerCount = $store->sellerOrders()
            ->join('orders', 'orders.id', '=', 'seller_orders.order_id')
            ->where('seller_orders.status', '!=', 'cancelled')
            ->distinct()
            ->count('orders.user_id');
        $canManageProducts = $this->hasRole($request, $tenant, ['owner', 'manager']);
        $canManageOrders = $this->hasRole($request, $tenant, ['owner', 'manager']);

        return view('seller.dashboard', [
            'store' => $store,
            'totalProducts' => (int) $productMetrics->total_products,
            'pendingOrderCount' => (int) $orderMetrics->pending_orders,
            'totalOrders' => (int) $orderMetrics->total_orders,
            'salesTotal' => $orderMetrics->sales_total ?? 0,
            'customerCount' => $customerCount,
            'outOfStockCount' => (int) $productMetrics->out_of_stock_products,
            'recentOrders' => $store->sellerOrders()
                ->with('order')
                ->withCount('items')
                ->latest()
                ->limit(5)
                ->get(),
            'recentProducts' => $store->products()
                ->with('images')
                ->latest('updated_at')
                ->limit(4)
                ->get(),
            'canManageProducts' => $canManageProducts,
            'canManageOrders' => $canManageOrders,
            'setupItems' => [
                ['label' => 'Store profile', 'complete' => filled($store->description)],
                ['label' => 'Store logo', 'complete' => filled($store->logo_path)],
                ['label' => 'Contact information', 'complete' => filled($store->phone) && filled($store->address_line) && filled($store->city)],
                ['label' => 'First product', 'complete' => $store->products()->exists()],
            ],
        ]);
    }

    /**
     * @param  array<int, string>  $roles
     */
    private function hasRole(Request $request, Tenant $tenant, array $roles): bool
    {
        return $request->user()->tenantMemberships()
            ->where('tenant_id', $tenant->id)
            ->whereIn('role', $roles)
            ->exists();
    }
}
