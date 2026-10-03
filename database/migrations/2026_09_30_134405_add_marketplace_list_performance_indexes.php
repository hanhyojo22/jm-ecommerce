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
        Schema::table('orders', function (Blueprint $table): void {
            $table->index(['user_id', 'created_at'], 'orders_user_created_at_index');
            $table->index(['user_id', 'status', 'created_at'], 'orders_user_status_created_at_index');
        });

        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->index(['tenant_id', 'created_at'], 'seller_orders_tenant_created_at_index');
            $table->index(['tenant_id', 'status', 'created_at'], 'seller_orders_tenant_status_created_at_index');
            $table->index(['store_id', 'status', 'created_at'], 'seller_orders_store_status_created_at_index');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->index(['store_id', 'status', 'created_at'], 'products_store_status_created_at_index');
            $table->index(['status', 'category_id', 'created_at'], 'products_status_category_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_store_status_created_at_index');
            $table->dropIndex('products_status_category_created_at_index');
        });

        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->dropIndex('seller_orders_tenant_created_at_index');
            $table->dropIndex('seller_orders_tenant_status_created_at_index');
            $table->dropIndex('seller_orders_store_status_created_at_index');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_user_created_at_index');
            $table->dropIndex('orders_user_status_created_at_index');
        });
    }
};
