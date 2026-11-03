<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            /*
             * A retried checkout must return the order the first attempt created instead of
             * placing a second one. The pair is unique per buyer; both columns stay null for
             * web checkouts, and databases treat those as distinct rows.
             */
            $table->string('client_request_id', 100)->nullable()->after('number');
            $table->string('client_request_hash', 64)->nullable()->after('client_request_id');
            $table->unique(['user_id', 'client_request_id'], 'orders_user_client_request_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_user_client_request_unique');
            $table->dropColumn(['client_request_id', 'client_request_hash']);
        });
    }
};
