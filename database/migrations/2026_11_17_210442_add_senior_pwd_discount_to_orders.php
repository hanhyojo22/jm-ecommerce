<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The senior citizen / PWD discount: kept per line so a partial return refunds what was
     * actually paid, and on the order with the card the branch checks at handover. The card
     * number is stored encrypted (text, since ciphertext is long).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('discount_amount', 12, 2)->default('0.00')->after('shipping_amount');
            $table->string('discount_card_type', 10)->nullable()->after('discount_amount');
            $table->text('discount_card_number')->nullable()->after('discount_card_type');
            $table->string('discount_card_name', 120)->nullable()->after('discount_card_number');
        });

        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->decimal('discount_amount', 12, 2)->default('0.00')->after('shipping_amount');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('discount_amount', 12, 2)->default('0.00')->after('line_total');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('discount_amount'));
        Schema::table('seller_orders', fn (Blueprint $table) => $table->dropColumn('discount_amount'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['discount_amount', 'discount_card_type', 'discount_card_number', 'discount_card_name']));
    }
};
