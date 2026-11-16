<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily hours as "HH:MM" Manila time; no hours means the branch is always open.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('opening_time', 5)->nullable()->after('delivery_radius_km');
            $table->string('closing_time', 5)->nullable()->after('opening_time');
            $table->unsignedSmallInteger('prep_minutes')->nullable()->after('closing_time');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['opening_time', 'closing_time', 'prep_minutes']);
        });
    }
};
