<?php

namespace App\Actions;

use App\Models\PlatformFeeStatement;
use App\Models\ReturnCaseOutcome;
use App\Models\SellerEarning;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IssuePlatformFeeStatements
{
    /**
     * Bill every store for the given month and return the statements issued.
     *
     * A statement charges the commission on every not-yet-billed earning delivered before
     * the month ended, less the credit on refunds recorded since the store's last statement.
     * When refunds outweigh the charges, nothing is due and the rest is credited to the next
     * statement. A store already billed for this month or a later one is skipped, so a second
     * run issues nothing and a late run for a skipped month cannot break the credit chain.
     *
     * @return EloquentCollection<int, PlatformFeeStatement>
     */
    public function handle(CarbonImmutable $month): EloquentCollection
    {
        $periodStart = $month->startOfMonth();
        $periodEnd = $periodStart->addMonthNoOverflow();

        $tenantIds = $this->unbilledEarnings($periodEnd)->distinct()->pluck('tenant_id')
            ->merge($this->refunds($periodEnd)->where('created_at', '>=', $periodStart)->get()->pluck('returnCase.sellerOrder.tenant_id'))
            ->unique()
            ->values();

        return new EloquentCollection($tenantIds
            ->map(fn (string $tenantId): ?PlatformFeeStatement => $this->bill($tenantId, $periodStart, $periodEnd))
            ->filter()
            ->values()
            ->all());
    }

    private function bill(string $tenantId, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): ?PlatformFeeStatement
    {
        return DB::transaction(function () use ($tenantId, $periodStart, $periodEnd): ?PlatformFeeStatement {
            $previous = $this->latestStatement($tenantId);

            if ($previous !== null && $previous->period_start->gte($periodStart)) {
                return null;
            }

            $earnings = $this->unbilledEarnings($periodEnd)->where('tenant_id', $tenantId)->orderBy('id')->lockForUpdate()->get();
            $refunds = $this->refundsSince($tenantId, $previous, $periodEnd)->get();

            $grossTotal = $this->sum($earnings->pluck('gross_amount'));
            $commissionTotal = $this->sum($earnings->pluck('commission_amount'));
            $refundCredit = $this->sum($refunds->pluck('commission_credit'));

            if (bccomp($commissionTotal, '0', 2) === 0 && bccomp($refundCredit, '0', 2) === 0) {
                return null;
            }

            $creditCarriedIn = (string) ($previous->credit_carried_out ?? '0.00');
            $balance = bcsub(bcsub($commissionTotal, $refundCredit, 2), $creditCarriedIn, 2);
            $owesSomething = bccomp($balance, '0', 2) > 0;

            $statement = PlatformFeeStatement::query()->create([
                'tenant_id' => $tenantId,
                'period_start' => $periodStart->toDateString(),
                'gross_total' => $grossTotal,
                'commission_total' => $commissionTotal,
                'refund_credit' => $refundCredit,
                'credit_carried_in' => $creditCarriedIn,
                'credit_carried_out' => $owesSomething ? '0.00' : bcmul($balance, '-1', 2),
                'amount_due' => $owesSomething ? $balance : '0.00',
                'status' => $owesSomething ? 'unpaid' : 'paid',
                'paid_at' => $owesSomething ? null : now(),
                // The last second of the due day, so a store paying on the date it was shown is not overdue.
                'due_at' => $periodEnd->addDays((int) config('marketplace.platform_fee.due_days'))->subSecond(),
            ]);

            SellerEarning::query()->whereKey($earnings->modelKeys())->update(['platform_fee_statement_id' => $statement->id]);

            return $statement;
        });
    }

    /**
     * @return Builder<SellerEarning>
     */
    private function unbilledEarnings(CarbonImmutable $periodEnd): Builder
    {
        return SellerEarning::query()
            ->whereNull('platform_fee_statement_id')
            // The online split already kept JMMart's fee, so only COD earnings are billed.
            ->where('fee_collected_at_payment', false)
            ->where('commission_amount', '>', 0)
            ->where('available_at', '<', $periodEnd);
    }

    /**
     * What the store's next statement looks like so far: fees on every delivered order not yet
     * billed, less refund credits since the last statement and any credit carried from it.
     *
     * @return array{commission: string, refund_credit: string, credit_carried_in: string, amount_due: string}
     */
    public function preview(string $tenantId): array
    {
        $previous = $this->latestStatement($tenantId);
        $commission = $this->sum(SellerEarning::query()->where('tenant_id', $tenantId)->whereNull('platform_fee_statement_id')->where('fee_collected_at_payment', false)->pluck('commission_amount'));
        $refundCredit = $this->sum($this->refundsSince($tenantId, $previous, CarbonImmutable::now()->addSecond())->pluck('commission_credit'));
        $creditCarriedIn = (string) ($previous->credit_carried_out ?? '0.00');
        $balance = bcsub(bcsub($commission, $refundCredit, 2), $creditCarriedIn, 2);

        return [
            'commission' => $commission,
            'refund_credit' => $refundCredit,
            'credit_carried_in' => $creditCarriedIn,
            'amount_due' => bccomp($balance, '0', 2) > 0 ? $balance : '0.00',
        ];
    }

    private function latestStatement(string $tenantId): ?PlatformFeeStatement
    {
        return PlatformFeeStatement::query()->where('tenant_id', $tenantId)->latest('period_start')->first();
    }

    /**
     * Refunds count by when they were recorded, not the paid date the seller typed, which can
     * be backdated into a month that was already billed. Everything recorded after the month
     * the last statement covered is credited, so a month that was never billed loses nothing.
     *
     * @return Builder<ReturnCaseOutcome>
     */
    private function refundsSince(string $tenantId, ?PlatformFeeStatement $previous, CarbonImmutable $periodEnd): Builder
    {
        return $this->refunds($periodEnd)
            ->when($previous !== null, fn (Builder $query) => $query->where('created_at', '>=', $previous->period_start->addMonthNoOverflow()))
            ->whereHas('returnCase.sellerOrder', fn ($query) => $query->where('tenant_id', $tenantId));
    }

    /**
     * @return Builder<ReturnCaseOutcome>
     */
    private function refunds(CarbonImmutable $periodEnd): Builder
    {
        return ReturnCaseOutcome::query()
            ->where('kind', 'refund')
            ->where('created_at', '<', $periodEnd)
            ->where('commission_credit', '>', 0)
            ->with('returnCase.sellerOrder');
    }

    /**
     * @param  Collection<int, mixed>  $amounts
     */
    private function sum(Collection $amounts): string
    {
        return $amounts->reduce(fn (string $total, $amount): string => bcadd($total, (string) $amount, 2), '0.00');
    }
}
