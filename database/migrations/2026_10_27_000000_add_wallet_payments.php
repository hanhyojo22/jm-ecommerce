<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Buyers can pay a store's own GCash or Maya account. The store holds that money just like COD
     * cash, so `cod_collected_at` keeps meaning "the store has the money" for every method.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_method_check');
        }
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('method', 20)->change();
        });
        Schema::table('stores', function (Blueprint $table): void {
            $table->string('gcash_number', 20)->nullable();
            $table->string('gcash_qr_path')->nullable();
            $table->string('maya_number', 20)->nullable();
            $table->string('maya_qr_path')->nullable();
        });
        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->string('payment_reference', 40)->nullable();
            $table->string('refund_reference', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->dropColumn(['payment_reference', 'refund_reference']);
        });
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropColumn(['gcash_number', 'gcash_qr_path', 'maya_number', 'maya_qr_path']);
        });
    }
};
