<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Onboarding refuses a store name already used in any letter case, but two applicants saving
     * at the same moment could both pass that check. These indexes are the backstop. A deleted
     * store gives its name back, so only live stores count.
     */
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX seller_applications_store_name_lower_unique ON seller_applications (LOWER(store_name))');
        DB::statement('CREATE UNIQUE INDEX stores_name_lower_unique ON stores (LOWER(name)) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS stores_name_lower_unique');
        DB::statement('DROP INDEX IF EXISTS seller_applications_store_name_lower_unique');
    }
};
