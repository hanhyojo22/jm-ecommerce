<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pharmacists' PRC license, required before they can review prescriptions, and the license
     * number each prescription was reviewed under (kept even after the license is renewed).
     */
    public function up(): void
    {
        Schema::table('tenant_members', function (Blueprint $table): void {
            $table->string('license_number', 20)->nullable()->after('is_pharmacist');
            $table->date('license_expires_on')->nullable()->after('license_number');
        });

        Schema::table('prescriptions', function (Blueprint $table): void {
            $table->string('reviewer_license_number', 20)->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table): void {
            $table->dropColumn('reviewer_license_number');
        });

        Schema::table('tenant_members', function (Blueprint $table): void {
            $table->dropColumn(['license_number', 'license_expires_on']);
        });
    }
};
