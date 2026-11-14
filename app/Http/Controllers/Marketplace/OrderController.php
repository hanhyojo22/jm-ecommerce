<?php

namespace App\Http\Controllers\Marketplace;

use App\Actions\StartOnlinePayment;
use App\Actions\UpdateSellerOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Queries\BuyerOrderQuery;
use App\Queries\RecommendationQuery;
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
        $this->authorize('viewAny', Order::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,confirmed,processing,shipped,delivered,cancelled'],
            'tab' => ['nullable', 'in:all,to_pay,awaiting_confirmation,to_dispatch,to_receive,delivered,to_review,returns'],
        ]);
        // shortcut: capped at 50 batches (500 orders) per request; switch to cursor batches if buyers outgrow it.
        $loadedBatches = min(50, max(1, (int) $request->query('page', 1)));
        $search = trim($filters['search'] ?? '');
        $tab = $filters['tab'] ?? 'all';
        $ownedTenantIds = BuyerOrderQuery::ownedTenantIds($request->user());
        $tabs = BuyerOrderQuery::TABS;

        $baseQuery = BuyerOrderQuery::base($request->user(), $search, $filters['status'] ?? null);

        $tabCounts = [];
        foreach (array_keys($tabs) as $key) {
            $tabCounts[$key] = BuyerOrderQuery::inTab(clone $baseQuery, $key, $ownedTenantIds)->count();
        }

        // What waits on the buyer right now, so the page can say so before any tab is opened.
        $attentionCounts = array_filter([
            'to_pay' => $tabCounts['to_pay'],
            'returns' => (clone $baseQuery)->whereHas('returnCases', BuyerOrderQuery::returnNeedsBuyer())->count(),
            'refunds' => BuyerOrderQuery::refundDue(clone $baseQuery)->count(),
            'to_receive' => $tabCounts['to_receive'],
        ]);

        $sellerOrders = BuyerOrderQuery::buyerFirst(
            BuyerOrderQuery::inTab(clone $baseQuery, $tab, $ownedTenantIds),
        )
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
            'ownedTenantIds' => $ownedTenantIds,
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorize('view', $order);

        $order = $order->load('sellerOrders.store', 'sellerOrders.issues', 'sellerOrders.returnCases', 'sellerOrders.items.product.images', 'sellerOrders.items.product.store', 'sellerOrders.items.review', 'payment');

        return view($request->header('X-Order-Modal') ? 'marketplace.orders._modal-ledger' : 'marketplace.orders.show', [
            'order' => $order,
            'ownedTenantIds' => BuyerOrderQuery::ownedTenantIds($request->user()),
        ]);
    }

    /**
     * Where checkout lands. It only describes a fresh order, so once any store has moved it the
     * order page, which tracks every step, takes over.
     */
    public function placed(Request $request, Order $order): RedirectResponse|View
    {
        $this->authorize('view', $order);
        $order->load('sellerOrders.store', 'sellerOrders.items.product.images', 'payment');

        if ($order->sellerOrders->contains(fn (SellerOrder $sellerOrder): bool => $sellerOrder->status !== 'pending')) {
            return to_route('orders.show', $order);
        }

        return view('marketplace.orders.placed', [
            'order' => $order,
            'recommendedProducts' => RecommendationQuery::forRequest($request),
        ]);
    }

    /**
     * Sends the buyer to a fresh PayMongo checkout for what they still owe. Reached right after
     * checkout and from "Pay now" on an unpaid order, so a closed tab never strands the order.
     */
    public function pay(Request $request, Order $order, StartOnlinePayment $startOnlinePayment): RedirectResponse|SymfonyRedirect
    {
        $this->authorize('pay', $order);

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
     * The buyer confirms the parcel arrived, so the store order is delivered.
     */
    public function received(Request $request, SellerOrder $sellerOrder, UpdateSellerOrderStatus $updateStatus): RedirectResponse
    {
        $this->authorize('receive', $sellerOrder);

        try {
            $updateStatus->handle($sellerOrder, 'delivered', $request->user(), $request->ip());
        } catch (ValidationException) {
            throw ValidationException::withMessages(['received' => 'We couldn’t mark this order received. It may already be delivered or have an open request; refresh to see its latest status.']);
        }

        return back()->with('status', 'Thanks for confirming your '.$sellerOrder->store->name.' order. You can now review your items.');
    }
}
