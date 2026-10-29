<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Online payments move from "pay the store's own account and send a reference" to one PayMongo
     * checkout split between the stores. A store gets online orders once its PayMongo child account
     * is activated; the reference numbers and the store's own wallet details are no longer used.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropColumn(['gcash_number', 'gcash_qr_path', 'maya_number', 'maya_qr_path', 'bank_name', 'bank_account_name', 'bank_number', 'bank_qr_path']);
            $table->string('paymongo_account_id', 64)->nullable()->unique();
            $table->string('paymongo_account_status', 20)->nullable();
        });

        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->dropColumn(['payment_reference', 'refund_reference']);
            $table->string('gateway_refund_id', 64)->nullable();
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('checkout_session_id', 64)->nullable()->unique();
            $table->string('gateway_payment_id', 64)->nullable();
            // What the buyer paid on top of the order to cover PayMongo's fee.
            $table->decimal('fee_amount', 12, 2)->default(0);
        });

        Schema::table('seller_earnings', function (Blueprint $table): void {
            // The split already kept JMMart's share and paid the store, so it is neither billed nor paid out.
            $table->boolean('fee_collected_at_payment')->default(false);
        });

        Schema::create('paymongo_events', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('type', 80);
            $table->timestamp('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paymongo_events');

        Schema::table('seller_earnings', function (Blueprint $table): void {
            $table->dropColumn('fee_collected_at_payment');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['checkout_session_id']);
            $table->dropColumn(['checkout_session_id', 'gateway_payment_id', 'fee_amount']);
        });

        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->dropColumn('gateway_refund_id');
            $table->string('payment_reference', 40)->nullable();
            $table->string('refund_reference', 40)->nullable();
        });

        Schema::table('stores', function (Blueprint $table): void {
            $table->dropUnique(['paymongo_account_id']);
            $table->dropColumn(['paymongo_account_id', 'paymongo_account_status']);
            $table->string('gcash_number', 20)->nullable();
            $table->string('gcash_qr_path')->nullable();
            $table->string('maya_number', 20)->nullable();
            $table->string('maya_qr_path')->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_name', 150)->nullable();
            $table->string('bank_number', 30)->nullable();
            $table->string('bank_qr_path')->nullable();
        });
    }
};
