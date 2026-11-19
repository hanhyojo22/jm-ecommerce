<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Other words customers type for one product, like symptoms or Filipino terms ("lagnat, ubo"),
     * stored lowercase and comma separated. Catalog search matches them as well as the name.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('search_keywords', 500)->nullable()->after('generic_name');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('search_keywords');
        });
    }
};
