<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Queries\AdminModerationQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);

        $dailyOrderCounts = Order::query()
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', today()->subDays(6))
            ->selectRaw('DATE(created_at) as day, COUNT(*) as orders')
            ->groupByRaw('DATE(created_at)')
            ->pluck('orders', 'day');
        $orderActivity = collect(range(6, 0))->map(function (int $daysAgo) use ($dailyOrderCounts): array {
            $date = today()->subDays($daysAgo);

            return ['day' => $date->format('D'), 'date' => $date->toDateString(), 'orders' => (int) $dailyOrderCounts->get($date->toDateString(), 0)];
        });
        $topStores = Store::query()
            ->whereHas('sellerOrders', fn (Builder $query) => $query->where('status', '!=', 'cancelled'))
            ->withSum(['sellerOrders as sales_total' => fn (Builder $query) => $query->where('status', '!=', 'cancelled')], 'total')
            ->orderByDesc('sales_total')
            ->limit(3)
            ->get();
        $topProducts = Product::query()
            ->whereHas('orderItems', fn (Builder $query) => $query->whereHas('sellerOrder', fn (Builder $sellerOrder) => $sellerOrder->where('status', '!=', 'cancelled')))
            ->withSum(['orderItems as sold_quantity' => fn (Builder $query) => $query->whereHas('sellerOrder', fn (Builder $sellerOrder) => $sellerOrder->where('status', '!=', 'cancelled'))], 'quantity')
            ->orderByDesc('sold_quantity')
            ->limit(3)
            ->get();
        $recentOrders = Order::query()->with('user:id,name')->latest()->limit(5)->get();

        return view('admin.overview', [
            'counts' => AdminModerationQuery::counts(),
            'marketplaceStats' => $this->marketplaceStats(),
            'orderActivity' => $orderActivity,
            'topStores' => $topStores,
            'topProducts' => $topProducts,
            'recentOrders' => $recentOrders,
        ]);
    }

    public function moderation(Request $request): View
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);

        $filters = $request->validate([
            'type' => ['nullable', 'in:all,application,appeal,product_report,chat_report'],
            'sort' => ['nullable', 'in:oldest,newest'],
        ]);
        $activeType = $filters['type'] ?? 'all';
        $activeSort = $filters['sort'] ?? 'oldest';
        $counts = AdminModerationQuery::counts();
        $queueItems = AdminModerationQuery::items($activeType, $activeSort)->map(function (array $item): array {
            [$url, $action] = match ($item['type']) {
                'application' => [route('admin.seller-applications.show', $item['id']), 'Review'],
                'appeal' => [route('admin.stores.show', $item['store_id']).'#suspension-appeals', 'Review appeal'],
                'product_report' => [route('admin.reviews.index', ['review' => $item['id']]).'#review-'.$item['id'], 'Review report'],
                'chat_report' => [route('admin.chat-reports.index', ['report' => $item['id']]).'#chat-report-'.$item['id'], 'Review report'],
            };

            return [...$item, 'url' => $url, 'action' => $action];
        });
        $dataCheckedAt = now();
        $marketplaceStats = $this->marketplaceStats();

        return view('dashboard', compact('activeSort', 'activeType', 'counts', 'dataCheckedAt', 'queueItems', 'marketplaceStats'));
    }

    /** @return array{gmv:float, orders:int, customers:int, activeSellers:int} */
    private function marketplaceStats(): array
    {
        return [
            'gmv' => (float) Order::query()->where('status', '!=', 'cancelled')->sum('total'),
            'orders' => Order::query()->where('status', '!=', 'cancelled')->count(),
            'customers' => User::query()->where('account_type', 'buyer')->count(),
            'activeSellers' => Tenant::query()->where('status', 'active')->count(),
        ];
    }
}
