<?php

namespace App\Http\Controllers\Seller;

use App\Actions\MarkCodPaymentPaid;
use App\Actions\UpdateSellerOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\SellerOrder;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SellerOrderController extends Controller
{
    /** Rows-per-page choices on the orders list; the first one is the default. */
    public const PER_PAGE_OPTIONS = [10, 20, 50];

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
        $perPage = in_array((int) $request->query('per_page'), self::PER_PAGE_OPTIONS, true) ? (int) $request->query('per_page') : self::PER_PAGE_OPTIONS[0];
        $orders = $ordersQuery->paginate($perPage)->onEachSide(1)->appends($request->except('order', 'page'));

        $orders->getCollection()->each(function (SellerOrder $sellerOrder) use ($canManageOrders): void {
            $sellerOrder->setAttribute(
                'available_transitions',
                $canManageOrders ? UpdateSellerOrderStatus::transitionsFor($sellerOrder->status) : [],
            );
        });

        return view('seller.orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'cashToCollectCount' => $tenant->sellerOrders()->where('status', 'delivered')->whereNull('cod_collected_at')->count(),
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
        $isModal = $request->header('X-Order-Modal') === '1';

        $sellerOrder = $tenant->sellerOrders()
            ->whereKey($sellerOrder)
            ->with($isModal ? ['items.product.images', 'order', 'issues', 'returnCases'] : ['items.product.images', 'order.payment', 'issues', 'returnCases.items.orderItem'])
            ->firstOrFail();
        $sellerOrder->setAttribute(
            'available_transitions',
            $canManageOrders ? ($sellerOrder->issues->contains(fn ($issue) => $issue->status === 'open' && $issue->type === 'cancellation') ? ['cancelled'] : UpdateSellerOrderStatus::transitionsFor($sellerOrder->status)) : [],
        );

        // The orders list opens an order in a modal and asks for just its body; a direct visit
        // (or a browser without JavaScript) still gets the full page.
        if ($isModal) {
            return view('seller.orders._modal', [
                'sellerOrder' => $sellerOrder,
                'canManageOrders' => $canManageOrders,
                'conversation' => Conversation::query()->where('seller_order_id', $sellerOrder->id)->first(),
            ]);
        }

        return view('seller.orders.show', compact('sellerOrder', 'canManageOrders'));
    }

    public function update(Request $request, SellerOrder $sellerOrder, UpdateSellerOrderStatus $updateStatus): RedirectResponse
    {
        $tenant = $this->activeTenant($request, ['owner', 'manager']);
        $sellerOrder = $tenant->sellerOrders()->whereKey($sellerOrder)->firstOrFail();

        $data = $request->validate([
            'status' => ['required', 'in:confirmed,processing,shipped,delivered,cancelled'],
        ]);

        $sellerOrder->load('order');
        $updateStatus->handle($sellerOrder, $data['status'], $request->user(), $request->ip());

        return back()->with('status', "Order {$sellerOrder->order->number} was marked as {$data['status']}.");
    }

    public function collectCod(Request $request, SellerOrder $sellerOrder, MarkCodPaymentPaid $action): RedirectResponse
    {
        $tenant = $this->activeTenant($request, ['owner', 'manager']);
        $sellerOrder = $tenant->sellerOrders()->whereKey($sellerOrder)->firstOrFail();
        try {
            $action->collectSellerOrder($request->user(), $sellerOrder, $request->ip());
        } catch (\LogicException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return back()->with('status', 'COD collection recorded for this store order.');
    }

    public function bulkConfirm(Request $request, UpdateSellerOrderStatus $updateStatus): RedirectResponse
    {
        $tenant = $this->activeTenant($request, ['owner', 'manager']);
        $data = $request->validate([
            'seller_order_ids' => ['required', 'array', 'min:1', 'max:40'],
            'seller_order_ids.*' => ['required', 'string', 'ulid'],
        ]);
        $ids = array_values(array_unique($data['seller_order_ids']));

        if (count($ids) > 20) {
            throw ValidationException::withMessages(['seller_order_ids' => 'Select no more than 20 orders.']);
        }

        DB::transaction(function () use ($tenant, $ids, $request, $updateStatus): void {
            $sellerOrders = $tenant->sellerOrders()->whereIn('id', $ids)->get()->keyBy('id');

            if ($sellerOrders->count() !== count($ids) || $sellerOrders->contains(fn (SellerOrder $order): bool => $order->status !== 'pending')) {
                throw ValidationException::withMessages(['seller_order_ids' => 'Only your pending orders can be confirmed. Refresh the list and try again.']);
            }

            foreach ($sellerOrders->sortBy('order_id') as $sellerOrder) {
                $updateStatus->handle($sellerOrder, 'confirmed', $request->user(), $request->ip());
            }
        });

        return to_route('seller.orders.index')->with('status', count($ids).' pending '.(count($ids) === 1 ? 'order was' : 'orders were').' confirmed.');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return HasMany<SellerOrder>
     */
    private function filteredOrders(Tenant $tenant, array $filters): HasMany
    {
        return $tenant->sellerOrders()
            ->with(['items.product.images', 'order.payment'])
            // Bulk confirm skips orders the buyer asked to cancel; confirming one would fail the batch.
            ->withExists(['issues as has_open_cancellation' => fn (Builder $query): Builder => $query->where('status', 'open')->where('type', 'cancellation')])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $search = '%'.mb_strtolower($search).'%';

                $query->whereHas('order', function (Builder $orderQuery) use ($search): void {
                    $orderQuery->whereRaw('LOWER(number) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(recipient_name) LIKE ?', [$search]);
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['payment_status'] ?? null, function (Builder $query, string $paymentStatus): void {
                if (in_array($paymentStatus, ['pending', 'paid'], true)) {
                    $paymentStatus === 'paid' ? $query->whereNotNull('cod_collected_at') : $query->whereNull('cod_collected_at');
                } else {
                    $query->whereHas('order.payment', fn (Builder $paymentQuery): Builder => $paymentQuery->where('status', $paymentStatus));
                }
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
