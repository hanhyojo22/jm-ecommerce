<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Orders are collected at the branch or delivered from it. Delivery costs one flat fee set by
     * head office, waived above an optional order amount, and keeps the pin the customer dropped.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('free_delivery_minimum', 12, 2)->nullable();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('fulfillment_method', 20)->default('delivery');
            $table->decimal('delivery_latitude', 10, 7)->nullable();
            $table->decimal('delivery_longitude', 10, 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['fulfillment_method', 'delivery_latitude', 'delivery_longitude']);
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['delivery_fee', 'free_delivery_minimum']);
        });
    }
};
