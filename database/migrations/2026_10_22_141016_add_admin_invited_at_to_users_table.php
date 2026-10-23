<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('admin_invited_at')->nullable()->after('platform_role');
        });

        // Existing administrators were created through the invitation flow. Keep
        // their first sign-in working while new administrator rows must be
        // explicitly marked as invited before Clerk email matching can link them.
        DB::table('users')
            ->where('account_type', 'admin')
            ->whereNull('clerk_user_id')
            ->whereNull('admin_invited_at')
            ->update(['admin_invited_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('admin_invited_at');
        });
    }
};
