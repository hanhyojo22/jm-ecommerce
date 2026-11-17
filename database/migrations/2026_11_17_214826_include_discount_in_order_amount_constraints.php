<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The Postgres amount checks predate the senior citizen / PWD discount and refused any
     * discounted order (total = subtotal + shipping). They now take the discount off, which
     * can never be negative or more than what it is taken from.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['orders', 'seller_orders'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_amounts_valid");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_amounts_valid CHECK (subtotal >= 0 AND shipping_amount >= 0 AND discount_amount >= 0 AND discount_amount <= subtotal AND total = subtotal - discount_amount + shipping_amount)");
        }

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_discount_valid CHECK (discount_amount >= 0 AND discount_amount <= line_total)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_discount_valid');

        foreach (['orders', 'seller_orders'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_amounts_valid");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_amounts_valid CHECK (subtotal >= 0 AND shipping_amount >= 0 AND total = subtotal + shipping_amount)");
        }
    }
};
