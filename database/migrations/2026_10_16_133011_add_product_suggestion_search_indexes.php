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

        DB::statement('CREATE INDEX products_name_prefix_index ON products (LOWER(name) text_pattern_ops)');
        DB::statement("CREATE INDEX products_name_words_index ON products USING gin (to_tsvector('simple', name))");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX products_name_words_index');
        DB::statement('DROP INDEX products_name_prefix_index');
    }
};
