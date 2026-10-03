<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->foreign(['store_id', 'tenant_id'], 'seller_orders_store_id_tenant_id_foreign')
                ->references(['id', 'tenant_id'])
                ->on('stores')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });

        Schema::table('seller_earnings', function (Blueprint $table): void {
            $table->foreign(['store_id', 'tenant_id'], 'seller_earnings_store_id_tenant_id_foreign')
                ->references(['id', 'tenant_id'])
                ->on('stores')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });

        Schema::table('seller_payouts', function (Blueprint $table): void {
            $table->foreign(['store_id', 'tenant_id'], 'seller_payouts_store_id_tenant_id_foreign')
                ->references(['id', 'tenant_id'])
                ->on('stores')
                ->restrictOnDelete()
                ->restrictOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seller_payouts', function (Blueprint $table): void {
            $table->dropForeign('seller_payouts_store_id_tenant_id_foreign');
        });

        Schema::table('seller_earnings', function (Blueprint $table): void {
            $table->dropForeign('seller_earnings_store_id_tenant_id_foreign');
        });

        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->dropForeign('seller_orders_store_id_tenant_id_foreign');
        });
    }
};
