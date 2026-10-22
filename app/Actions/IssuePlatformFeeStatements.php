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
     * the month ended, less 1% of the refunds recorded during the month. When refunds
     * outweigh the charges, nothing is due and the rest is credited to the next statement.
     * A store with a statement for the month is skipped, so a second run issues nothing.
     *
     * @return EloquentCollection<int, PlatformFeeStatement>
     */
    public function handle(CarbonImmutable $month): EloquentCollection
    {
        $periodStart = $month->startOfMonth();
        $periodEnd = $periodStart->addMonthNoOverflow();

        $tenantIds = $this->unbilledEarnings($periodEnd)->distinct()->pluck('tenant_id')
            ->merge($this->refunds($periodStart, $periodEnd)->get()->pluck('returnCase.sellerOrder.tenant_id'))
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
            $alreadyBilled = PlatformFeeStatement::query()
                ->where('tenant_id', $tenantId)
                ->whereDate('period_start', $periodStart->toDateString())
                ->exists();

            if ($alreadyBilled) {
                return null;
            }

            $earnings = $this->unbilledEarnings($periodEnd)->where('tenant_id', $tenantId)->orderBy('id')->lockForUpdate()->get();
            $refunds = $this->refunds($periodStart, $periodEnd)
                ->whereHas('returnCase.sellerOrder', fn ($query) => $query->where('tenant_id', $tenantId))
                ->get();

            $grossTotal = $this->sum($earnings->pluck('gross_amount'));
            $commissionTotal = $this->sum($earnings->pluck('commission_amount'));
            $refundCredit = $this->sum($refunds->pluck('commission_credit'));

            if (bccomp($commissionTotal, '0', 2) === 0 && bccomp($refundCredit, '0', 2) === 0) {
                return null;
            }

            $creditCarriedIn = (string) (PlatformFeeStatement::query()
                ->where('tenant_id', $tenantId)
                ->whereDate('period_start', '<', $periodStart->toDateString())
                ->latest('period_start')
                ->value('credit_carried_out') ?? '0.00');
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
                'due_at' => $periodEnd->addDays((int) config('marketplace.platform_fee.due_days')),
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
            ->where('commission_amount', '>', 0)
            ->where('available_at', '<', $periodEnd);
    }

    /**
     * Refunds count in the month they were recorded, not the paid date the seller typed,
     * which can be backdated into a month that was already billed.
     *
     * @return Builder<ReturnCaseOutcome>
     */
    private function refunds(CarbonImmutable $periodStart, CarbonImmutable $periodEnd): Builder
    {
        return ReturnCaseOutcome::query()
            ->where('kind', 'refund')
            ->where('created_at', '>=', $periodStart)
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
