<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The bank account buyers transfer to when they pay a store by bank transfer. It is shown to
     * buyers, unlike the payout profile, which is only where JMMart would pay the store.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_name', 150)->nullable();
            $table->string('bank_number', 30)->nullable();
            $table->string('bank_qr_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropColumn(['bank_name', 'bank_account_name', 'bank_number', 'bank_qr_path']);
        });
    }
};
