<?php

namespace App\Http\Controllers\Marketplace;

use App\Actions\StartOnlinePayment;
use App\Actions\UpdateSellerOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ReturnCase;
use App\Models\SellerOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,confirmed,processing,shipped,delivered,cancelled'],
            'tab' => ['nullable', 'in:all,to_pay,awaiting_confirmation,to_dispatch,to_receive,delivered,to_review,returns'],
        ]);
        // shortcut: capped at 50 batches (500 orders) per request; switch to cursor batches if buyers outgrow it.
        $loadedBatches = min(50, max(1, (int) $request->query('page', 1)));
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

        $onlineMethods = implode(', ', array_fill(0, count(Payment::ONLINE_METHODS), '?'));
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
                'issues' => fn ($query) => $query->latest(),
                'returnCases' => fn ($query) => $query->latest('id'),
            ])
            // Orders waiting on the buyer lead, so a payment owed never slips to a later page.
            ->orderByRaw(
                "case when (seller_orders.status = 'pending' and seller_orders.cod_collected_at is null and exists (select 1 from payments where payments.order_id = seller_orders.order_id and payments.method in ({$onlineMethods})))
                    or (seller_orders.status = 'cancelled' and seller_orders.gateway_refund_id is null and seller_orders.cod_collected_at is not null and exists (select 1 from payments where payments.order_id = seller_orders.order_id and payments.method in ({$onlineMethods})))
                    or exists ({$buyerTurnCase->toSql()}) then 0
                    when seller_orders.status = 'shipped' then 1 else 2 end",
                [...Payment::ONLINE_METHODS, ...Payment::ONLINE_METHODS, ...$buyerTurnCase->getBindings()],
            )
            ->latest()
            // "Load more" reads ?page=N as "the first N batches", so back and refresh rebuild the same list.
            ->paginate(10 * $loadedBatches, ['*'], 'page', 1);

        return view('marketplace.orders.index', [
            'sellerOrders' => $sellerOrders,
            'loadMoreUrl' => $sellerOrders->hasMorePages() && $loadedBatches < 50 ? $request->fullUrlWithQuery(['page' => $loadedBatches + 1]) : null,
            'filters' => $filters,
            'tab' => $tab,
            'tabs' => $tabs,
            'tabCounts' => $tabCounts,
            'attentionCounts' => $attentionCounts,
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
     * A store waits for the buyer's online payment before it confirms, so these orders are the
     * buyer's to move, not the store's.
     */
    private function needingPayment(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->whereNull('cod_collected_at')
            ->whereHas('order.payment', fn (Builder $paymentQuery) => $paymentQuery->whereIn('method', Payment::ONLINE_METHODS));
    }

    /**
     * A store order cancelled after the buyer paid online whose PayMongo refund has not gone through.
     */
    private function refundDue(Builder $query): Builder
    {
        return $query->where('status', 'cancelled')
            ->whereNull('gateway_refund_id')
            ->whereNotNull('cod_collected_at')
            ->whereHas('order.payment', fn (Builder $paymentQuery) => $paymentQuery->whereIn('method', Payment::ONLINE_METHODS));
    }

    public function show(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        $order = $order->load('sellerOrders.store', 'sellerOrders.issues', 'sellerOrders.returnCases', 'sellerOrders.items.product.images', 'sellerOrders.items.product.store', 'sellerOrders.items.review', 'payment');

        if ($request->header('X-Order-Modal')) {
            return view('marketplace.orders._modal-ledger', ['order' => $order]);
        }

        return view('marketplace.orders.show', ['order' => $order]);
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
     * Sends the buyer to a fresh PayMongo checkout for what they still owe. Reached right after
     * checkout and from "Pay now" on an unpaid order, so a closed tab never strands the order.
     */
    public function pay(Request $request, Order $order, StartOnlinePayment $startOnlinePayment): RedirectResponse|SymfonyRedirect
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        try {
            return redirect()->away($startOnlinePayment->handle($order));
        } catch (ValidationException $exception) {
            return to_route('orders.show', $order)->withErrors($exception->errors());
        } catch (Throwable $exception) {
            report($exception);

            return to_route('orders.show', $order)->withErrors(['payment' => 'We couldn’t open the payment page. Please try again in a few minutes.']);
        }
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
