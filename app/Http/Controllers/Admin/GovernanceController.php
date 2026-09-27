<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\SellerApplication;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Queries\AdminStoreQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GovernanceController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);
    }

    public function stores(Request $request): View
    {
        $this->authorizeAdmin($request);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
            'joined' => ['nullable', 'in:last_30_days,last_90_days,this_year'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'sort' => ['nullable', 'in:name,newest,recently_active'],
        ]);

        $stores = AdminStoreQuery::base()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                AdminStoreQuery::applySearch($query, $search);
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['joined'] ?? null, function (Builder $query, string $joined): void {
                match ($joined) {
                    'last_30_days' => $query->where('created_at', '>=', now()->subDays(30)),
                    'last_90_days' => $query->where('created_at', '>=', now()->subDays(90)),
                    'this_year' => $query->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]),
                };
            })
            ->when($filters['category_id'] ?? null, fn (Builder $query, string $categoryId): Builder => $query->whereHas('products', fn (Builder $productQuery): Builder => $productQuery->where('category_id', $categoryId)));

        match ($filters['sort'] ?? 'newest') {
            'name' => $stores->orderBy('name')->orderBy('id'),
            'recently_active' => $stores->latest('updated_at'),
            default => $stores->latest(),
        };

        $statusCounts = AdminStoreQuery::statusCounts();

        return view('admin.governance.stores', [
            'stores' => $stores->paginate(15)->withQueryString(),
            'filters' => $filters,
            'totalStores' => AdminStoreQuery::total(),
            'statusCounts' => $statusCounts,
            'pendingReviewCount' => AdminStoreQuery::pendingReviewCount(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function showStore(Request $request, Store $store): View
    {
        $this->authorizeAdmin($request);
        $this->authorize('view', $store);

        $store = Store::query()
            ->with(['tenant.members.user'])
            ->withCount(['products', 'sellerOrders'])
            ->whereKey($store)
            ->firstOrFail();

        $owner = $store->tenant->members->firstWhere('role', 'owner')?->user
            ?? $store->tenant->members->first()?->user;

        $application = SellerApplication::query()
            ->with(['user', 'tenant'])
            ->where('tenant_id', $store->tenant_id)
            ->latest()
            ->first();

        if ($application === null && $owner !== null) {
            $application = SellerApplication::query()
                ->with(['user', 'tenant'])
                ->where('user_id', $owner->id)
                ->where('status', 'approved')
                ->latest('reviewed_at')
                ->first();
        }

        $products = Product::query()
            ->with('category')
            ->where('store_id', $store->id)
            ->latest()
            ->paginate(10, ['*'], 'products_page')
            ->withQueryString();

        $sellerOrders = SellerOrder::query()
            ->with(['order.payment'])
            ->where('store_id', $store->id)
            ->latest()
            ->paginate(10, ['*'], 'orders_page')
            ->withQueryString();

        $auditLogs = AuditLog::query()
            ->with('actor')
            ->where(function (Builder $query) use ($store, $application): void {
                $query->where(function (Builder $storeQuery) use ($store): void {
                    $storeQuery->where('entity_type', Store::class)->where('entity_id', $store->id);
                })->orWhere(function (Builder $tenantQuery) use ($store): void {
                    $tenantQuery->where('entity_type', Tenant::class)->where('entity_id', $store->tenant_id);
                });

                if ($application !== null) {
                    $query->orWhere(function (Builder $applicationQuery) use ($application): void {
                        $applicationQuery->where('entity_type', SellerApplication::class)->where('entity_id', $application->id);
                    });
                }
            })
            ->latest('created_at')
            ->limit(20)
            ->get();

        return view('admin.governance.store-show', [
            'store' => $store,
            'owner' => $owner,
            'application' => $application,
            'products' => $products,
            'sellerOrders' => $sellerOrders,
            'auditLogs' => $auditLogs,
        ]);
    }

    public function customers(Request $request): View
    {
        $this->authorizeAdmin($request);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'registered' => ['nullable', 'in:last_30_days,last_90_days,this_year'],
            'verification' => ['nullable', 'in:verified,unverified'],
            'sort' => ['nullable', 'in:newest,oldest,name'],
        ]);

        $customers = User::query()
            ->where('account_type', 'buyer')
            ->withCount('orders')
            ->withSum([
                'orders as total_spent' => fn (Builder $query): Builder => $query
                    ->where('status', '!=', 'cancelled')
                    ->whereHas('payment', fn (Builder $paymentQuery): Builder => $paymentQuery->where('status', 'paid')),
            ], 'total')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $search = '%'.mb_strtolower($search).'%';

                $query->where(function (Builder $customerQuery) use ($search): void {
                    $customerQuery->whereRaw('LOWER(name) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$search]);
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('is_active', $status === 'active'))
            ->when($filters['verification'] ?? null, function (Builder $query, string $verification): void {
                if ($verification === 'unverified') {
                    $query->whereNull('email_verified_at');

                    return;
                }

                $query->whereNotNull('email_verified_at');
            })
            ->when($filters['registered'] ?? null, function (Builder $query, string $registered): void {
                match ($registered) {
                    'last_30_days' => $query->where('created_at', '>=', now()->subDays(30)),
                    'last_90_days' => $query->where('created_at', '>=', now()->subDays(90)),
                    'this_year' => $query->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]),
                };
            });

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $customers->oldest(),
            'name' => $customers->orderBy('name')->orderBy('id'),
            default => $customers->latest(),
        };

        return view('admin.governance.customers', [
            'customers' => $customers->paginate(15)->withQueryString(),
            'filters' => $filters,
            'totalCustomers' => User::query()->where('account_type', 'buyer')->count(),
        ]);
    }

    public function showCustomer(Request $request, User $customer): View
    {
        $this->authorizeAdmin($request);

        $customer = User::query()
            ->where('account_type', 'buyer')
            ->whereKey($customer)
            ->withCount('orders')
            ->withSum([
                'orders as total_spent' => fn (Builder $query): Builder => $query
                    ->where('status', '!=', 'cancelled')
                    ->whereHas('payment', fn (Builder $paymentQuery): Builder => $paymentQuery->where('status', 'paid')),
            ], 'total')
            ->firstOrFail();

        return view('admin.governance.customer-show', [
            'customer' => $customer,
            'orders' => $customer->orders()->with('payment')->latest()->paginate(10)->withQueryString(),
        ]);
    }

    public function auditLogs(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('admin.governance.audit-logs', ['logs' => AuditLog::query()->with('actor')->latest('created_at')->paginate(50)]);
    }
}
