<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Each store keeps the fee policy version and rate it agreed to, so raising the rate never
     * charges a store more until it accepts the new terms. Every store accepted so far agreed
     * to version 2.0 at 1%, the only terms that have existed.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('platform_fee_policy_version', 20)->nullable();
            $table->decimal('platform_fee_rate', 5, 4)->nullable();
        });

        DB::table('tenants')->whereNotNull('platform_fee_accepted_at')->update(['platform_fee_policy_version' => '2.0', 'platform_fee_rate' => '0.0100']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['platform_fee_policy_version', 'platform_fee_rate']);
        });
    }
};
