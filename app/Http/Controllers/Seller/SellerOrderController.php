<?php

namespace App\Http\Controllers\Seller;

use App\Actions\UpdateSellerOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\SellerOrder;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SellerOrderController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $this->activeTenant($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,confirmed,processing,shipped,delivered,cancelled'],
            'payment_status' => ['nullable', 'in:pending,paid,failed,refunded'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $canManageOrders = $this->canManageOrders($request, $tenant);

        $ordersQuery = $this->filteredOrders($tenant, $filters);
        $orders = $ordersQuery->paginate(20)->withQueryString();

        $orders->getCollection()->each(function (SellerOrder $sellerOrder) use ($canManageOrders): void {
            $sellerOrder->setAttribute(
                'available_transitions',
                $canManageOrders ? UpdateSellerOrderStatus::transitionsFor($sellerOrder->status) : [],
            );
        });

        return view('seller.orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'statusCounts' => $tenant->sellerOrders()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'canManageOrders' => $canManageOrders,
        ]);
    }

    public function show(Request $request, SellerOrder $sellerOrder): View
    {
        $tenant = $this->activeTenant($request);
        $canManageOrders = $this->canManageOrders($request, $tenant);

        $sellerOrder = $tenant->sellerOrders()
            ->whereKey($sellerOrder)
            ->with(['items.product.images', 'order.payment'])
            ->firstOrFail();
        $sellerOrder->setAttribute(
            'available_transitions',
            $canManageOrders ? UpdateSellerOrderStatus::transitionsFor($sellerOrder->status) : [],
        );

        return view('seller.orders.show', compact('sellerOrder', 'canManageOrders'));
    }

    public function update(Request $request, SellerOrder $sellerOrder, UpdateSellerOrderStatus $updateStatus): RedirectResponse
    {
        abort_unless($this->activeTenant($request, ['owner', 'manager'])->id === $sellerOrder->tenant_id, 403);

        $data = $request->validate([
            'status' => ['required', 'in:confirmed,processing,shipped,delivered,cancelled'],
        ]);

        $sellerOrder->load('order');
        $updateStatus->handle($sellerOrder, $data['status'], $request->user(), $request->ip());

        return back()->with('status', "Order {$sellerOrder->order->number} was marked as {$data['status']}.");
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return HasMany<SellerOrder>
     */
    private function filteredOrders(Tenant $tenant, array $filters): HasMany
    {
        return $tenant->sellerOrders()
            ->with(['items.product.images', 'order.payment'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $search = '%'.mb_strtolower($search).'%';

                $query->whereHas('order', function (Builder $orderQuery) use ($search): void {
                    $orderQuery->whereRaw('LOWER(number) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(recipient_name) LIKE ?', [$search]);
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['payment_status'] ?? null, function (Builder $query, string $paymentStatus): void {
                $query->whereHas('order.payment', fn (Builder $paymentQuery): Builder => $paymentQuery->where('status', $paymentStatus));
            })
            ->when($filters['from'] ?? null, fn (Builder $query, string $from): Builder => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to): Builder => $query->whereDate('created_at', '<=', $to))
            ->latest();
    }

    private function canManageOrders(Request $request, Tenant $tenant): bool
    {
        return $request->user()->tenantMemberships()
            ->where('tenant_id', $tenant->id)
            ->whereIn('role', ['owner', 'manager'])
            ->exists();
    }

    /**
     * @param  array<int, string>  $roles
     */
    private function activeTenant(Request $request, array $roles = ['owner', 'manager', 'staff']): Tenant
    {
        return $request->user()->sellerTenant($roles) ?? abort(403);
    }
}
