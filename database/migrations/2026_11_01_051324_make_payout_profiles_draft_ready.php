<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A payout destination is only required when step 6 is continued, not while it is being filled in.
     * Draft autosave can therefore record a method or a provider before the account number exists.
     */
    public function up(): void
    {
        Schema::table('payout_profiles', function (Blueprint $table): void {
            $table->string('method', 20)->nullable()->change();
            $table->string('holder_name')->nullable()->change();
            $table->text('account_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payout_profiles', function (Blueprint $table): void {
            $table->string('method', 20)->nullable(false)->change();
            $table->string('holder_name')->nullable(false)->change();
            $table->text('account_number')->nullable(false)->change();
        });
    }
};
