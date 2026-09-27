<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $t->string('number')->unique();
            $t->enum('status', ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $t->string('recipient_name');
            $t->string('phone', 30);
            $t->string('address_line');
            $t->string('city', 100);
            $t->decimal('subtotal', 12, 2);
            $t->decimal('shipping_amount', 12, 2)->default(0);
            $t->decimal('total', 12, 2);
            $t->timestamps();
        });
        Schema::create('seller_orders', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->foreignUlid('order_id')->constrained()->cascadeOnDelete();
            $t->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $t->foreignUlid('store_id')->constrained()->restrictOnDelete();
            $t->enum('status', ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $t->decimal('subtotal', 12, 2);
            $t->decimal('shipping_amount', 12, 2)->default(0);
            $t->decimal('total', 12, 2);
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->foreignUlid('seller_order_id')->constrained()->cascadeOnDelete();
            $t->foreignUlid('product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('product_name');
            $t->string('sku');
            $t->decimal('unit_price', 12, 2);
            $t->unsignedInteger('quantity');
            $t->decimal('line_total', 12, 2);
            $t->timestamps();
        });
        Schema::create('payments', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->foreignUlid('order_id')->unique()->constrained()->cascadeOnDelete();
            $t->enum('method', ['cod']);
            $t->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $t->decimal('amount', 12, 2);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('seller_orders');
        Schema::dropIfExists('orders');
    }
};
