<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->string('name');
            $t->string('slug')->unique();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::table('products', function (Blueprint $t): void {
            $t->foreignUlid('category_id')->nullable()->after('store_id')->constrained()->nullOnDelete();
            $t->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('category_id');
        });
        Schema::dropIfExists('categories');
    }
};
