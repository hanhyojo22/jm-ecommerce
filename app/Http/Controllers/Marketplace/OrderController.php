<?php

namespace App\Http\Controllers\Marketplace;

use App\Actions\UpdateSellerOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ReturnCase;
use App\Models\SellerOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,confirmed,processing,shipped,delivered,cancelled'],
            'tab' => ['nullable', 'in:all,to_pay,awaiting_confirmation,to_dispatch,to_receive,delivered,to_review,returns'],
        ]);
        $search = trim($filters['search'] ?? '');
        $searchTerm = '%'.mb_strtolower($search).'%';
        $tab = $filters['tab'] ?? 'all';
        $ownedTenantIds = $request->user()->tenantMemberships()
            ->whereIn('role', ['owner', 'manager', 'staff'])
            ->pluck('tenant_id')->all();
        $tabs = [
            'all' => 'All',
            'to_pay' => 'To pay',
            'awaiting_confirmation' => 'Awaiting store',
            'to_dispatch' => 'Preparing',
            'to_receive' => 'On the way',
            'delivered' => 'Delivered',
            'to_review' => 'To review',
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

        // What waits on the buyer right now, so the page can say so before any tab is opened.
        $attentionCounts = array_filter([
            'to_pay' => $tabCounts['to_pay'],
            'returns' => (clone $baseQuery)->whereHas('returnCases', $this->returnNeedsBuyer(...))->count(),
            'refunds' => $this->refundDue(clone $baseQuery)->count(),
            'to_receive' => $tabCounts['to_receive'],
        ]);

        $directMethods = implode(', ', array_fill(0, count(Payment::DIRECT_METHODS), '?'));
        $buyerTurnCase = ReturnCase::query()->selectRaw('1')
            ->whereColumn('return_cases.seller_order_id', 'seller_orders.id')
            ->where($this->returnNeedsBuyer(...));

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
                'returnCases' => fn ($query) => $query->latest('id'),
            ])
            // Orders waiting on the buyer lead, so a payment owed never slips to a later page.
            ->orderByRaw(
                "case when (seller_orders.status = 'pending' and seller_orders.payment_reference is null and seller_orders.cod_collected_at is null and exists (select 1 from payments where payments.order_id = seller_orders.order_id and payments.method in ({$directMethods})))
                    or (seller_orders.status = 'cancelled' and seller_orders.refund_reference is null and (seller_orders.payment_reference is not null or seller_orders.cod_collected_at is not null) and exists (select 1 from payments where payments.order_id = seller_orders.order_id and payments.method in ({$directMethods})))
                    or exists ({$buyerTurnCase->toSql()}) then 0
                    when seller_orders.status = 'shipped' then 1 else 2 end",
                [...Payment::DIRECT_METHODS, ...Payment::DIRECT_METHODS, ...$buyerTurnCase->getBindings()],
            )
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
            'attentionCounts' => $attentionCounts,
            'reviewableItemIds' => $reviewableItemIds,
        ]);
    }

    /**
     * @param  array<int, string>  $ownedTenantIds
     */
    private function inTab(Builder $query, string $tab, array $ownedTenantIds): Builder
    {
        return match ($tab) {
            'to_pay' => $this->needingPayment($query),
            'awaiting_confirmation' => $query->where('status', 'pending')->whereNot(fn (Builder $unpaid) => $this->needingPayment($unpaid)),
            'to_dispatch' => $query->whereIn('status', ['confirmed', 'processing']),
            'to_receive' => $query->where('status', 'shipped'),
            'delivered' => $query->where('status', 'delivered'),
            'to_review' => $query->where('status', 'delivered')
                ->whereNotIn('tenant_id', $ownedTenantIds)
                ->whereHas('items', fn (Builder $itemQuery) => $itemQuery->whereNotNull('product_id')->whereDoesntHave('review')),
            'returns' => $query->where(fn (Builder $returnQuery) => $returnQuery->whereHas('returnCases')->orWhereHas('issues', fn (Builder $issueQuery) => $issueQuery->where('type', 'return_refund'))),
            default => $query,
        };
    }

    /**
     * A store waits for a direct GCash, Maya, or bank payment before it confirms, so these orders
     * are the buyer's to move, not the store's.
     */
    private function needingPayment(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->whereNull('payment_reference')
            ->whereNull('cod_collected_at')
            ->whereHas('order.payment', fn (Builder $paymentQuery) => $paymentQuery->whereIn('method', Payment::DIRECT_METHODS));
    }

    /**
     * A store cancelled after the buyer paid it directly and has not recorded the refund yet.
     */
    private function refundDue(Builder $query): Builder
    {
        return $query->where('status', 'cancelled')
            ->whereNull('refund_reference')
            ->where(fn (Builder $paid) => $paid->whereNotNull('payment_reference')->orWhereNotNull('cod_collected_at'))
            ->whereHas('order.payment', fn (Builder $paymentQuery) => $paymentQuery->whereIn('method', Payment::DIRECT_METHODS));
    }

    public function show(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return view('marketplace.orders.show', ['order' => $order->load('sellerOrders.store', 'sellerOrders.issues', 'sellerOrders.returnCases', 'sellerOrders.items.product.images', 'sellerOrders.items.product.store', 'sellerOrders.items.review', 'payment')]);
    }

    /**
     * Where checkout lands. It only describes a fresh order, so once any store has moved it the
     * order page, which tracks every step, takes over.
     */
    public function placed(Request $request, Order $order): RedirectResponse|View
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        $order->load('sellerOrders.store', 'sellerOrders.items.product.images', 'payment');

        if ($order->sellerOrders->contains(fn (SellerOrder $sellerOrder): bool => $sellerOrder->status !== 'pending')) {
            return to_route('orders.show', $order);
        }

        return view('marketplace.orders.placed', ['order' => $order]);
    }

    /**
     * The buyer sent this store its GCash/Maya payment and gives the transfer's reference number,
     * so the store can find it in its own wallet before confirming. It can be corrected until then.
     */
    public function paymentReference(Request $request, SellerOrder $sellerOrder): RedirectResponse
    {
        abort_unless($sellerOrder->order()->value('user_id') === $request->user()->id, 404);
        $data = $request->validate(['payment_reference' => ['required', 'string', 'max:40', 'regex:/^[0-9A-Za-z -]+$/']]);

        DB::transaction(function () use ($sellerOrder, $data): void {
            $sellerOrder = SellerOrder::query()->with('order.payment')->lockForUpdate()->findOrFail($sellerOrder->id);

            if (! in_array($sellerOrder->order->payment?->method, Payment::DIRECT_METHODS, true) || $sellerOrder->status !== 'pending' || $sellerOrder->cod_collected_at !== null) {
                throw ValidationException::withMessages(['payment_reference' => 'This store order is not awaiting a payment reference.']);
            }

            $sellerOrder->update(['payment_reference' => trim($data['payment_reference'])]);
        });

        // Sent from the guided dialog, the order page reopens it on the next store still to pay.
        return to_route('orders.show', $sellerOrder->order_id)
            ->with('status', 'Reference sent. The store will confirm once the payment shows in its account.')
            ->with('guided_pay', $request->boolean('guided'));
    }

    /**
     * Return cases where the next step is the buyer's: decide or send the item back before their deadline,
     * or escalate a store that let its own deadline pass.
     */
    private function returnNeedsBuyer(Builder $caseQuery): Builder
    {
        // Only the latest case counts, because that is the one the purchase card shows.
        return $caseQuery->whereRaw('return_cases.id = (select max(latest.id) from return_cases as latest where latest.seller_order_id = seller_orders.id)')
            ->where(fn (Builder $turn): Builder => $turn
                ->where(fn (Builder $leg): Builder => $leg->whereIn('status', ['offered', 'rejected'])
                    ->where(fn (Builder $open): Builder => $open->whereNull('buyer_respond_by')->orWhere('buyer_respond_by', '>=', now())))
                ->orWhere('status', 'awaiting_confirmation')
                ->orWhere('status', 'awaiting_return')
                ->orWhere(fn (Builder $leg): Builder => $leg->where('status', 'awaiting_seller')->where('respond_by', '<', now())));
    }

    /**
     * The buyer confirms the parcel arrived, so the store order is delivered.
     */
    public function received(Request $request, SellerOrder $sellerOrder, UpdateSellerOrderStatus $updateStatus): RedirectResponse
    {
        abort_unless($sellerOrder->order()->value('user_id') === $request->user()->id, 404);

        try {
            $updateStatus->handle($sellerOrder, 'delivered', $request->user(), $request->ip());
        } catch (ValidationException) {
            throw ValidationException::withMessages(['received' => 'We couldn’t mark this order received. It may already be delivered or have an open request; refresh to see its latest status.']);
        }

        return back()->with('status', 'Thanks for confirming your '.$sellerOrder->store->name.' order. You can now review your items.');
    }
}
