<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who took a delivery out, so the buyer can see and call them.
     */
    public function up(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->string('rider_name', 100)->nullable()->after('shipped_at');
            $table->string('rider_phone', 30)->nullable()->after('rider_name');
        });
    }

    public function down(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropColumn(['rider_name', 'rider_phone']);
        });
    }
};
