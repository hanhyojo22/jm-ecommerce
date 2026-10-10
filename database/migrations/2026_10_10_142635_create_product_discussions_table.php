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
        Schema::create('product_discussions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('parent_id')->nullable();
            $table->foreignUlid('root_id')->nullable();
            $table->string('author_type', 10);
            $table->text('body');
            $table->string('status', 20)->default('published');
            $table->text('moderation_reason')->nullable();
            $table->foreignUlid('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['product_id', 'parent_id', 'status', 'created_at']);
            $table->index(['root_id', 'status', 'created_at']);
        });

        Schema::table('product_discussions', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('product_discussions')->cascadeOnDelete();
            $table->foreign('root_id')->references('id')->on('product_discussions')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_discussions');
    }
};
