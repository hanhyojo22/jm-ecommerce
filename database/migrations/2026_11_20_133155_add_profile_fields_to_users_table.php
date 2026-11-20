<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer profile details the app asks for once. name_confirmed_at marks a name the
     * customer typed, which Clerk updates no longer overwrite.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->date('birth_date')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('sex', 10)->nullable();
            $table->timestamp('name_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['birth_date', 'phone', 'sex', 'name_confirmed_at']);
        });
    }
};
