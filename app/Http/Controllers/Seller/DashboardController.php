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
        $productCounts = $store->products()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $pendingOrderCount = $store->sellerOrders()->where('status', 'pending')->count();
        $totalOrders = $store->sellerOrders()->count();
        $outOfStockCount = $store->products()->where('stock_quantity', 0)->count();
        $canManageProducts = $this->hasRole($request, $tenant, ['owner', 'manager']);
        $canManageOrders = $this->hasRole($request, $tenant, ['owner', 'manager']);

        return view('seller.dashboard', [
            'store' => $store,
            'productCounts' => $productCounts,
            'totalProducts' => $store->products()->count(),
            'pendingOrderCount' => $pendingOrderCount,
            'totalOrders' => $totalOrders,
            'outOfStockCount' => $outOfStockCount,
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
