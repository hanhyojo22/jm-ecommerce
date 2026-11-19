<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The company rebranded to Everyday Pharmacy: rename the company, its branches and the demo
     * product brand that still say JMMart. Rows already renamed (live) are left alone.
     */
    public function up(): void
    {
        foreach (['tenants' => 'name', 'stores' => 'name', 'products' => 'brand'] as $table => $column) {
            DB::table($table)
                ->where($column, 'like', 'JMMart%')
                ->update([$column => DB::raw("replace({$column}, 'JMMart', 'Everyday')"), 'updated_at' => now()]);
        }
    }

    /**
     * Names are data; there is nothing to restore.
     */
    public function down(): void {}
};
