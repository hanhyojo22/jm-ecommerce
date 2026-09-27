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
            $table->string('account_type', 20)->default('buyer')->index();
            $table->boolean('is_active')->default(true)->index();
        });

        DB::table('users')
            ->whereIn('id', DB::table('seller_applications')->select('user_id'))
            ->orWhereIn('id', DB::table('tenant_members')->select('user_id'))
            ->update(['account_type' => 'seller']);

        DB::table('users')
            ->whereIn('platform_role', ['super_admin', 'admin'])
            ->update(['account_type' => 'admin']);

        DB::table('users')
            ->where('platform_role', 'moderator')
            ->update([
                'account_type' => 'admin',
                'platform_role' => 'admin',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['account_type']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['account_type', 'is_active']);
        });
    }
};
