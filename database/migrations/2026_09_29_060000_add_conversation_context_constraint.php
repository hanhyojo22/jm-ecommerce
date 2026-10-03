<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE conversations ADD CONSTRAINT conversations_exactly_one_context CHECK ((product_id IS NOT NULL)::integer + (seller_order_id IS NOT NULL)::integer = 1)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE conversations DROP CONSTRAINT conversations_exactly_one_context');
    }
};
