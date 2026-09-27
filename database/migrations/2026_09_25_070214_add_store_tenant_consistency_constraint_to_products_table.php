<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->unique(['id', 'tenant_id'], 'stores_id_tenant_id_unique');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreign(['store_id', 'tenant_id'], 'products_store_id_tenant_id_foreign')
                ->references(['id', 'tenant_id'])
                ->on('stores')
                ->cascadeOnDelete()
                ->restrictOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign('products_store_id_tenant_id_foreign');
        });

        Schema::table('stores', function (Blueprint $table): void {
            $table->dropUnique('stores_id_tenant_id_unique');
        });
    }
};
