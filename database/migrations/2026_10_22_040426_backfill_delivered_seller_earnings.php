<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $rate = (string) config('marketplace.platform_fee.rate');

        DB::table('seller_orders')
            ->join('orders', 'orders.id', '=', 'seller_orders.order_id')
            ->leftJoin('payments', 'payments.order_id', '=', 'orders.id')
            ->join('tenants', 'tenants.id', '=', 'seller_orders.tenant_id')
            ->leftJoin('seller_earnings', 'seller_earnings.seller_order_id', '=', 'seller_orders.id')
            ->whereNull('seller_earnings.id')
            ->where('seller_orders.status', 'delivered')
            ->orderBy('seller_orders.id')
            ->select([
                'seller_orders.id',
                'seller_orders.tenant_id',
                'seller_orders.store_id',
                'seller_orders.total',
                'seller_orders.delivered_at',
                'tenants.platform_fee_accepted_at',
                'payments.method as payment_method',
            ])
            ->each(function (object $sellerOrder) use ($rate): void {
                $deliveredAt = $sellerOrder->delivered_at ?? now();
                $feeApplies = $sellerOrder->platform_fee_accepted_at !== null
                    && Carbon::parse($sellerOrder->platform_fee_accepted_at)->lessThanOrEqualTo(Carbon::parse($deliveredAt));
                $commissionRate = $feeApplies ? $rate : '0';
                $gross = (string) $sellerOrder->total;
                $commission = bcadd(bcmul($gross, $commissionRate, 6), '0.005', 2);

                DB::table('seller_earnings')->insertOrIgnore([
                    'id' => (string) Str::ulid(),
                    'seller_order_id' => $sellerOrder->id,
                    'tenant_id' => $sellerOrder->tenant_id,
                    'store_id' => $sellerOrder->store_id,
                    'gross_amount' => $gross,
                    'commission_rate' => $commissionRate,
                    'commission_amount' => $commission,
                    'net_amount' => bcsub($gross, $commission, 2),
                    'status' => 'available',
                    'available_at' => $deliveredAt,
                    'voided_at' => $sellerOrder->payment_method === 'cod' ? now() : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Ledger entries are financial records and must not be removed by rollback.
    }
};
