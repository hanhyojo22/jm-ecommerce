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
        Schema::table('products', function (Blueprint $table): void {
            $table->string('brand', 100)->nullable();
            $table->string('condition', 20)->default('new');
            $table->boolean('free_shipping')->default(true);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->index(['status', 'brand']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['status', 'brand']);
            $table->dropColumn(['brand', 'condition', 'free_shipping', 'compare_at_price']);
        });
    }
};
