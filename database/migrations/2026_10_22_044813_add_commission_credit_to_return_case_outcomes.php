<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Existing refunds get the credit the monthly statement would have computed for them:
     * the refund at the order's commission rate, rounded half up, never above the commission charged.
     */
    public function up(): void
    {
        Schema::table('return_case_outcomes', function (Blueprint $table): void {
            $table->decimal('commission_credit', 12, 2)->default(0);
        });

        DB::table('return_case_outcomes')
            ->join('return_cases', 'return_cases.id', '=', 'return_case_outcomes.return_case_id')
            ->join('seller_earnings', 'seller_earnings.seller_order_id', '=', 'return_cases.seller_order_id')
            ->where('return_case_outcomes.kind', 'refund')
            ->whereNotNull('return_case_outcomes.amount')
            ->orderBy('return_case_outcomes.id')
            ->select(['return_case_outcomes.id', 'return_case_outcomes.amount', 'seller_earnings.commission_rate', 'seller_earnings.commission_amount'])
            ->each(function (object $refund): void {
                $credit = bcadd(bcmul((string) $refund->amount, (string) $refund->commission_rate, 6), '0.005', 2);
                $cap = (string) $refund->commission_amount;

                DB::table('return_case_outcomes')
                    ->where('id', $refund->id)
                    ->update(['commission_credit' => bccomp($credit, $cap, 2) > 0 ? $cap : $credit]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_case_outcomes', function (Blueprint $table): void {
            $table->dropColumn('commission_credit');
        });
    }
};
