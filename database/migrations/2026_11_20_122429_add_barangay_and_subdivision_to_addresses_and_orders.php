<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable because older addresses and orders have no barangay; the address form requires it.
     */
    public function up(): void
    {
        foreach (['delivery_addresses', 'orders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('subdivision', 150)->nullable();
                $table->string('barangay', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['delivery_addresses', 'orders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['subdivision', 'barangay']);
            });
        }
    }
};
