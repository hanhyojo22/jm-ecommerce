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
        Schema::create('seller_payouts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('store_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('reference')->unique();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['recorded'])->default('recorded');
            $table->text('note')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['tenant_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_payouts');
    }
};
