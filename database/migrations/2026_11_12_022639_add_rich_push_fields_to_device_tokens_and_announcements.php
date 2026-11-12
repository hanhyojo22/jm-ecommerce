<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->boolean('rich_notifications')->default(false);
        });
        Schema::table('announcements', function (Blueprint $table): void {
            $table->string('image_url', 1000)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->dropColumn('rich_notifications');
        });
        Schema::table('announcements', function (Blueprint $table): void {
            $table->dropColumn('image_url');
        });
    }
};
