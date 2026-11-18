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
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('discount_document_path')->nullable();
            $table->string('discount_document_name')->nullable();
            $table->string('discount_document_mime', 50)->nullable();
        });
        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->foreignUlid('discount_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('discount_reviewed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seller_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('discount_reviewed_by');
            $table->dropColumn('discount_reviewed_at');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['discount_document_path', 'discount_document_name', 'discount_document_mime']);
        });
    }
};
