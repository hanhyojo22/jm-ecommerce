<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pharmacy product fields: the drug class (null = not a medicine; otc, rx, dangerous), the FDA
     * registration number, storage instructions, and units per pack for each pack-size variant.
     * Existing products keep today's behaviour: a prescription product becomes rx, and one with a
     * generic name (which is what gave the SC/PWD discount) becomes otc.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('drug_class', 20)->nullable();
            $table->string('fda_registration_number', 40)->nullable();
            $table->string('storage_instructions', 200)->nullable();
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->decimal('pack_units', 8, 2)->nullable();
        });

        DB::table('products')->where('requires_prescription', true)->update(['drug_class' => 'rx']);
        DB::table('products')->whereNull('drug_class')->whereNotNull('generic_name')->where('generic_name', '!=', '')->update(['drug_class' => 'otc']);
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn('pack_units');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['drug_class', 'fda_registration_number', 'storage_instructions']);
        });
    }
};
