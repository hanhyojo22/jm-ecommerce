<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Pharmacist status now needs a PRC license on file, but setup used to make every owner a
     * pharmacist without one. Those people could never review a prescription, so they stop being
     * listed as pharmacists; the owner re-adds them with a license on the Staff page.
     */
    public function up(): void
    {
        DB::table('tenant_members')
            ->where('is_pharmacist', true)
            ->where(fn ($query) => $query->whereNull('license_number')->orWhere('license_number', '')->orWhereNull('license_expires_on'))
            ->update(['is_pharmacist' => false]);
    }

    /**
     * The cleared flags are not recorded, so there is nothing to restore.
     */
    public function down(): void {}
};
