<?php

namespace App\Http\Controllers\Admin;

use App\Actions\MarkCodPaymentPaid;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SellerOrder;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use LogicException;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,confirmed,processing,shipped,delivered,cancelled'],
            'payment_status' => ['nullable', 'in:pending,paid,failed,refunded'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $orders = Order::query()
            ->with([
                'user:id,name,email',
                'payment:id,order_id,method,status,amount',
                'sellerOrders' => fn ($query) => $query
                    ->withSum('items as item_quantity', 'quantity')
                    ->with(['store.tenant.members.user']),
            ])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $search = '%'.mb_strtolower($search).'%';

                $query->where(function (Builder $orderQuery) use ($search): void {
                    $orderQuery->whereRaw('LOWER(number) LIKE ?', [$search])
                        ->orWhereHas('user', fn (Builder $userQuery): Builder => $userQuery
                            ->whereRaw('LOWER(name) LIKE ?', [$search])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$search]))
                        ->orWhereHas('sellerOrders.store', fn (Builder $storeQuery): Builder => $storeQuery
                            ->whereRaw('LOWER(name) LIKE ?', [$search]))
                        ->orWhereHas('sellerOrders.store.tenant.members.user', fn (Builder $sellerQuery): Builder => $sellerQuery
                            ->whereRaw('LOWER(name) LIKE ?', [$search]));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status): Builder => $query
                ->whereHas('payment', fn (Builder $paymentQuery): Builder => $paymentQuery->where('status', $status)))
            ->when($filters['store_id'] ?? null, fn (Builder $query, string $storeId): Builder => $query
                ->whereHas('sellerOrders', fn (Builder $sellerOrderQuery): Builder => $sellerOrderQuery->where('store_id', $storeId)))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from): Builder => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to): Builder => $query->whereDate('created_at', '<=', $to))
            ->latest()
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'statusCounts' => Order::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'totalOrders' => Order::query()->count(),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);

        $order = Order::query()
            ->with([
                'user:id,name,email',
                'payment:id,order_id,method,status,amount',
                'sellerOrders.store.tenant.members.user',
                'sellerOrders.items.product.images',
                'sellerOrders.issues',
            ])
            ->whereKey($order)
            ->firstOrFail();

        $auditLogs = AuditLog::query()
            ->with('actor')
            ->where(function (Builder $query) use ($order): void {
                $query->where(function (Builder $orderQuery) use ($order): void {
                    $orderQuery->where('entity_type', Order::class)->where('entity_id', $order->id);
                })->orWhere(function (Builder $paymentQuery) use ($order): void {
                    $paymentQuery->where('entity_type', Payment::class)->where('entity_id', $order->payment->id);
                });
            })
            ->latest('created_at')
            ->get();

        return view('admin.orders.show', compact('order', 'auditLogs'));
    }

    public function markCodPaymentPaid(Request $request, Order $order, MarkCodPaymentPaid $action): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);

        try {
            $action->handle($request->user(), $order, $request->ip());
        } catch (LogicException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return to_route('admin.orders.show', $order)->with('status', 'COD payment marked as paid.');
    }

    public function markSellerCodCollected(Request $request, SellerOrder $sellerOrder, MarkCodPaymentPaid $action): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);
        try {
            $action->collectSellerOrder($request->user(), $sellerOrder, $request->ip());
        } catch (LogicException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return back()->with('status', 'COD collection corrected for this seller order.');
    }
}
