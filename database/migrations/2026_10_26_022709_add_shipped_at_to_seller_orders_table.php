<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Orders already shipped count from their last update, the closest record of when they shipped.
     */
    public function up(): void
    {
        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->timestamp('shipped_at')->nullable();
            $table->index(['status', 'shipped_at']);
        });

        DB::table('seller_orders')->where('status', 'shipped')->update(['shipped_at' => DB::raw('updated_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->dropIndex(['status', 'shipped_at']);
            $table->dropColumn('shipped_at');
        });
    }
};
