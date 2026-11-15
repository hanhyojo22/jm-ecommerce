<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One pharmacy company adds its own branches, so seller sign-up and suspension appeals go (children first).
     */
    public function up(): void
    {
        foreach (['store_suspension_appeals', 'seller_application_status_histories', 'seller_application_reviews', 'policy_acceptances', 'payout_profiles', 'seller_documents', 'seller_applications'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Seller onboarding is not coming back; restore an older backup if its data is ever needed.
     */
    public function down(): void {}
};
