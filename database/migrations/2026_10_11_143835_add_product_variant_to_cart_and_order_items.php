<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropUnique(['cart_id', 'product_id']);
            $table->foreignUlid('product_variant_id')->nullable()->after('product_id')->constrained()->restrictOnDelete();
            $table->string('variant_key', 32)->default('default')->after('product_variant_id');
            $table->unique(['cart_id', 'product_id', 'variant_key']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignUlid('product_variant_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->string('variant_label')->nullable()->after('sku');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropColumn('variant_label');
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropUnique(['cart_id', 'product_id', 'variant_key']);
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropColumn('variant_key');
            $table->unique(['cart_id', 'product_id']);
        });
    }
};
