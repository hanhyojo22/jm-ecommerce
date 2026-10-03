<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('seller_orders')
            ->join('orders', 'orders.id', '=', 'seller_orders.order_id')
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->leftJoin('seller_earnings', 'seller_earnings.seller_order_id', '=', 'seller_orders.id')
            ->whereNull('seller_earnings.id')
            ->where('orders.status', 'delivered')
            ->where('payments.status', 'paid')
            ->where('seller_orders.status', 'delivered')
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')
                    ->from('seller_orders as delivery_check')
                    ->whereColumn('delivery_check.order_id', 'seller_orders.order_id')
                    ->where('delivery_check.status', '!=', 'delivered');
            })
            ->orderBy('seller_orders.id')
            ->select([
                'seller_orders.id',
                'seller_orders.tenant_id',
                'seller_orders.store_id',
                'seller_orders.total',
                'payments.updated_at as paid_at',
            ])
            ->each(function (object $sellerOrder): void {
                DB::table('seller_earnings')->insert([
                    'id' => (string) Str::ulid(),
                    'seller_order_id' => $sellerOrder->id,
                    'tenant_id' => $sellerOrder->tenant_id,
                    'store_id' => $sellerOrder->store_id,
                    'gross_amount' => $sellerOrder->total,
                    'commission_amount' => '0.00',
                    'net_amount' => $sellerOrder->total,
                    'status' => 'available',
                    'available_at' => $sellerOrder->paid_at,
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
