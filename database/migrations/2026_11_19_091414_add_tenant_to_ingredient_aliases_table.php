<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The company whose head office added the name. Null marks the built-in names everyone shares,
     * which no company can remove.
     */
    public function up(): void
    {
        Schema::table('ingredient_aliases', function (Blueprint $table): void {
            $table->foreignUlid('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ingredient_aliases', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
