<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_review_responses', function (Blueprint $table) {
            $table->dropUnique(['product_review_id']);
            $table->foreignUlid('parent_response_id')->nullable()->constrained('product_review_responses')->cascadeOnDelete();
            $table->string('author_type', 10)->default('seller');
            $table->index(['product_review_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('product_review_responses', function (Blueprint $table) {
            $table->dropIndex(['product_review_id', 'created_at', 'id']);
            $table->dropConstrainedForeignId('parent_response_id');
            $table->dropColumn('author_type');
            $table->unique('product_review_id');
        });
    }
};
