<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A failed delivery support blames on the buyer counts as a refused COD parcel. Refusals
     * before support last lifted a buyer's limit no longer count against them.
     */
    public function up(): void
    {
        Schema::table('order_issues', function (Blueprint $table): void {
            $table->boolean('counts_as_refusal')->default(false);
            $table->index(['buyer_id', 'counts_as_refusal', 'updated_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('cod_limit_lifted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('cod_limit_lifted_at');
        });

        Schema::table('order_issues', function (Blueprint $table): void {
            $table->dropIndex(['buyer_id', 'counts_as_refusal', 'updated_at']);
            $table->dropColumn('counts_as_refusal');
        });
    }
};
