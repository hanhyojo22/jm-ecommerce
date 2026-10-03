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
        Schema::create('seller_earnings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('seller_order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('store_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('seller_payout_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->enum('status', ['available', 'paid_out'])->default('available');
            $table->timestamp('available_at');
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'available_at']);
            $table->index(['store_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_earnings');
    }
};
