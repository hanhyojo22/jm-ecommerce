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
            $table->index(['status', 'updated_at'], 'seller_orders_status_updated_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->dropIndex('seller_orders_status_updated_at_index');
        });
    }
};
