<?php

namespace App\Queries;

use App\Models\Payment;
use App\Models\ReturnCase;
use App\Models\SellerOrder;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * The buyer's seller-order list, shared by the web "My purchases" page and the app.
 */
class BuyerOrderQuery
{
    /** @var array<string, string> */
    public const TABS = [
        'all' => 'All',
        'to_pay' => 'To pay',
        'awaiting_confirmation' => 'Awaiting store',
        'to_dispatch' => 'Preparing',
        'to_receive' => 'On the way',
        'delivered' => 'Delivered',
        'to_review' => 'To review',
        'returns' => 'Returns',
    ];

    public static function base(User $user, string $search = '', ?string $status = null): Builder
    {
        $searchTerm = '%'.mb_strtolower($search).'%';

        return SellerOrder::query()
            ->whereHas('order', fn (Builder $query) => $query->where('user_id', $user->id))
            ->when($status !== null, fn (Builder $query) => $query->whereHas('order', fn (Builder $orderQuery) => $orderQuery->where('status', $status)))
            ->when($search !== '', function (Builder $query) use ($searchTerm): void {
                $query->where(function (Builder $searchQuery) use ($searchTerm): void {
                    $searchQuery->whereHas('order', fn (Builder $orderQuery) => $orderQuery->whereRaw('LOWER(number) LIKE ?', [$searchTerm]))
                        ->orWhereHas('store', fn (Builder $storeQuery) => $storeQuery->whereRaw('LOWER(name) LIKE ?', [$searchTerm]))
                        ->orWhereHas('items', fn (Builder $itemQuery) => $itemQuery->whereRaw('LOWER(product_name) LIKE ?', [$searchTerm]));
                });
            });
    }

    /**
     * Tenants the buyer works in, so a store they manage is never suggested for review.
     *
     * @return array<int, string>
     */
    public static function ownedTenantIds(User $user): array
    {
        return $user->tenantMemberships()
            ->whereIn('role', ['owner', 'manager', 'staff'])
            ->pluck('tenant_id')->all();
    }

    /**
     * @param  array<int, string>  $ownedTenantIds
     */
    public static function inTab(Builder $query, string $tab, array $ownedTenantIds): Builder
    {
        return match ($tab) {
            'to_pay' => self::needingPayment($query),
            'awaiting_confirmation' => $query->where('status', 'pending')->whereNot(fn (Builder $unpaid) => self::needingPayment($unpaid)),
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
    public static function needingPayment(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->whereNull('cod_collected_at')
            ->whereHas('order.payment', fn (Builder $paymentQuery) => $paymentQuery->whereIn('method', Payment::ONLINE_METHODS));
    }

    /**
     * A store order cancelled after the buyer paid online whose PayMongo refund has not gone through.
     */
    public static function refundDue(Builder $query): Builder
    {
        return $query->where('status', 'cancelled')
            ->whereNull('gateway_refund_id')
            ->whereNotNull('cod_collected_at')
            ->whereHas('order.payment', fn (Builder $paymentQuery) => $paymentQuery->whereIn('method', Payment::ONLINE_METHODS));
    }

    /**
     * Return cases where the next step is the buyer's: decide or send the item back before their deadline,
     * or escalate a store that let its own deadline pass.
     *
     * @return Closure(Builder): (Builder)
     */
    public static function returnNeedsBuyer(): Closure
    {
        return function (Builder $caseQuery): Builder {
            // Only the latest case counts, because that is the one the purchase card shows.
            return $caseQuery->whereRaw('return_cases.id = (select max(latest.id) from return_cases as latest where latest.seller_order_id = seller_orders.id)')
                ->where(fn (Builder $turn): Builder => $turn
                    ->where(fn (Builder $leg): Builder => $leg->whereIn('status', ['offered', 'rejected'])
                        ->where(fn (Builder $open): Builder => $open->whereNull('buyer_respond_by')->orWhere('buyer_respond_by', '>=', now())))
                    ->orWhere('status', 'awaiting_confirmation')
                    ->orWhere('status', 'awaiting_return')
                    ->orWhere(fn (Builder $leg): Builder => $leg->where('status', 'awaiting_seller')->where('respond_by', '<', now())));
        };
    }

    /**
     * Orders waiting on the buyer lead, so a payment owed never slips to a later page.
     */
    public static function buyerFirst(Builder $query): Builder
    {
        $onlineMethods = implode(', ', array_fill(0, count(Payment::ONLINE_METHODS), '?'));
        $buyerTurnCase = ReturnCase::query()->selectRaw('1')
            ->whereColumn('return_cases.seller_order_id', 'seller_orders.id')
            ->where(self::returnNeedsBuyer());

        return $query->orderByRaw(
            "case when (seller_orders.status = 'pending' and seller_orders.cod_collected_at is null and exists (select 1 from payments where payments.order_id = seller_orders.order_id and payments.method in ({$onlineMethods})))
                or (seller_orders.status = 'cancelled' and seller_orders.gateway_refund_id is null and seller_orders.cod_collected_at is not null and exists (select 1 from payments where payments.order_id = seller_orders.order_id and payments.method in ({$onlineMethods})))
                or exists ({$buyerTurnCase->toSql()}) then 0
                when seller_orders.status = 'shipped' then 1 else 2 end",
            [...Payment::ONLINE_METHODS, ...Payment::ONLINE_METHODS, ...$buyerTurnCase->getBindings()],
        );
    }
}
