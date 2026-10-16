<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_addresses', function (Blueprint $table): void {
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 4)->nullable();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 4)->nullable();
            $table->text('delivery_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['province', 'postal_code', 'delivery_note']);
        });

        Schema::table('delivery_addresses', function (Blueprint $table): void {
            $table->dropColumn(['province', 'postal_code']);
        });
    }
};
