<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\StartOnlinePayment;
use App\Actions\UpdateSellerOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use App\Http\Resources\Api\V1\SellerOrderResource;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Queries\BuyerOrderQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'])],
            'tab' => ['nullable', Rule::in(array_keys(BuyerOrderQuery::TABS))],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ]);

        $tab = $filters['tab'] ?? 'all';
        $ownedTenantIds = BuyerOrderQuery::ownedTenantIds($request->user());
        $baseQuery = BuyerOrderQuery::base(
            $request->user(),
            trim($filters['search'] ?? ''),
            $filters['status'] ?? null,
        );

        $tabCounts = collect(array_keys(BuyerOrderQuery::TABS))
            ->mapWithKeys(fn (string $key): array => [
                $key => BuyerOrderQuery::inTab(clone $baseQuery, $key, $ownedTenantIds)->count(),
            ])
            ->all();

        $sellerOrders = BuyerOrderQuery::buyerFirst(
            BuyerOrderQuery::inTab(clone $baseQuery, $tab, $ownedTenantIds),
        )
            ->with([
                'order' => fn ($query) => $query->select('id', 'user_id', 'number', 'total', 'created_at')->withCount('sellerOrders'),
                'order.payment:id,order_id,method,status,amount,fee_amount',
                'store:id,name,slug,logo_path',
                'items.product:id,store_id,slug',
                'items.product.thumbnail',
                'items.review',
                'items.product.store:id,slug',
                'issues' => fn ($query) => $query->latest(),
                'returnCases' => fn ($query) => $query->latest('id'),
            ])
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 10))
            ->withQueryString();

        return SellerOrderResource::collection($sellerOrders)
            ->additional(['tab' => $tab, 'tab_counts' => $tabCounts])
            ->response($request);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order->load([
            'sellerOrders.store',
            'sellerOrders.issues' => fn ($query) => $query->latest(),
            'sellerOrders.returnCases' => fn ($query) => $query->latest('id'),
            'sellerOrders.items.review',
            'sellerOrders.storeRating',
            'payment',
        ]);

        return OrderResource::make($order)->response($request);
    }

    public function pay(Request $request, Order $order, StartOnlinePayment $startOnlinePayment): JsonResponse
    {
        $this->authorize('pay', $order);

        $data = $request->validate([
            'success_url' => ['nullable', 'url:http,https'],
            'cancel_url' => ['nullable', 'url:http,https'],
        ]);

        try {
            $url = $startOnlinePayment->handle(
                $order,
                $data['success_url'] ?? null,
                $data['cancel_url'] ?? null,
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'payment' => 'We couldn’t open the payment page. Please try again in a few minutes.',
            ]);
        }

        return response()->json(['data' => ['payment_url' => $url]]);
    }

    public function received(Request $request, SellerOrder $sellerOrder, UpdateSellerOrderStatus $updateStatus): JsonResponse
    {
        $this->authorize('receive', $sellerOrder);

        try {
            $updateStatus->handle($sellerOrder, 'delivered', $request->user(), $request->ip());
        } catch (ValidationException) {
            throw ValidationException::withMessages(['received' => 'We couldn’t mark this order received. It may already be delivered or have an open request; refresh to see its latest status.']);
        }

        $sellerOrder->refresh()->load(['store:id,name,slug,logo_path', 'items', 'issues', 'returnCases', 'order', 'storeRating']);

        return SellerOrderResource::make($sellerOrder)->response($request);
    }
}
