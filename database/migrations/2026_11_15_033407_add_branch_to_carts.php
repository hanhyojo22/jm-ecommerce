<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A cart buys from one branch: the one the shopper picked (or nearest). Clearing a closed
     * branch leaves the cart to pick another rather than deleting it.
     */
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->foreignUlid('store_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('store_id');
        });
    }
};
