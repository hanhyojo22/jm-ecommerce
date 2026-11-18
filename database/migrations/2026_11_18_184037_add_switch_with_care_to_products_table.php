<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Medicines where changing brands needs a pharmacist's or doctor's say (levothyroxine,
     * anti-seizure medicines, warfarin). Every brand is still listed; customers are warned first.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('switch_with_care')->default(false)->after('requires_prescription');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('switch_with_care');
        });
    }
};
