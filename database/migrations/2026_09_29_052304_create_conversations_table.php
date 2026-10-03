<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('seller_order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('latest_message_at')->nullable();
            $table->timestamps();
            $table->unique(['buyer_id', 'product_id']);
            $table->unique(['buyer_id', 'seller_order_id']);
            $table->index(['store_id', 'latest_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
