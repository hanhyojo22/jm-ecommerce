<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('ALTER TABLE cart_items ADD CONSTRAINT cart_items_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_amounts_valid CHECK (unit_price > 0 AND quantity > 0 AND line_total = unit_price * quantity)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_amounts_valid CHECK (subtotal >= 0 AND shipping_amount >= 0 AND total = subtotal + shipping_amount)');
        DB::statement('ALTER TABLE seller_orders ADD CONSTRAINT seller_orders_amounts_valid CHECK (subtotal >= 0 AND shipping_amount >= 0 AND total = subtotal + shipping_amount)');
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_non_negative CHECK (amount >= 0)');
        DB::statement('ALTER TABLE product_reviews ADD CONSTRAINT product_reviews_rating_valid CHECK (rating BETWEEN 1 AND 5)');
        DB::statement('CREATE INDEX products_name_trgm_index ON products USING gin (LOWER(name) gin_trgm_ops)');
        DB::statement('CREATE INDEX products_sku_trgm_index ON products USING gin (LOWER(sku) gin_trgm_ops)');
        DB::statement('CREATE INDEX products_description_trgm_index ON products USING gin (LOWER(description) gin_trgm_ops)');
        DB::statement('CREATE INDEX stores_name_trgm_index ON stores USING gin (LOWER(name) gin_trgm_ops)');
        DB::statement('CREATE INDEX tenants_name_trgm_index ON tenants USING gin (LOWER(name) gin_trgm_ops)');
        DB::statement('CREATE INDEX users_name_trgm_index ON users USING gin (LOWER(name) gin_trgm_ops)');
        DB::statement('CREATE INDEX users_email_trgm_index ON users USING gin (LOWER(email) gin_trgm_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX users_email_trgm_index');
        DB::statement('DROP INDEX users_name_trgm_index');
        DB::statement('DROP INDEX tenants_name_trgm_index');
        DB::statement('DROP INDEX stores_name_trgm_index');
        DB::statement('DROP INDEX products_description_trgm_index');
        DB::statement('DROP INDEX products_sku_trgm_index');
        DB::statement('DROP INDEX products_name_trgm_index');
        DB::statement('ALTER TABLE product_reviews DROP CONSTRAINT product_reviews_rating_valid');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_amount_non_negative');
        DB::statement('ALTER TABLE seller_orders DROP CONSTRAINT seller_orders_amounts_valid');
        DB::statement('ALTER TABLE orders DROP CONSTRAINT orders_amounts_valid');
        DB::statement('ALTER TABLE order_items DROP CONSTRAINT order_items_amounts_valid');
        DB::statement('ALTER TABLE cart_items DROP CONSTRAINT cart_items_quantity_positive');
    }
};
