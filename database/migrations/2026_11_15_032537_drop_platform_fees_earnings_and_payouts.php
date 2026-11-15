<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One pharmacy company keeps its own money: no platform fee, no seller earnings or payouts,
     * and online payments go to its own PayMongo account instead of a per-store split.
     */
    public function up(): void
    {
        Schema::dropIfExists('seller_earnings');
        Schema::dropIfExists('platform_fee_statements');
        Schema::dropIfExists('seller_payouts');

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['platform_fee_accepted_at', 'platform_fee_policy_version', 'platform_fee_rate']);
        });

        Schema::table('stores', function (Blueprint $table): void {
            $table->dropUnique(['paymongo_account_id']);
            $table->dropColumn(['paymongo_account_id', 'paymongo_account_status']);
        });

        Schema::table('return_case_outcomes', function (Blueprint $table): void {
            $table->dropColumn('commission_credit');
        });
    }

    /**
     * The marketplace money model is not coming back; restore an older backup if its data is ever needed.
     */
    public function down(): void {}
};
