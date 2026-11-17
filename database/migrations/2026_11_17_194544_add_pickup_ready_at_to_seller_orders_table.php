<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a pickup order was first marked ready at the counter. Kept apart from shipped_at,
     * which also covers delivery orders, so the buyer page can say when to collect it.
     */
    public function up(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->timestamp('pickup_ready_at')->nullable()->after('shipped_at');
        });
    }

    public function down(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropColumn('pickup_ready_at');
        });
    }
};
