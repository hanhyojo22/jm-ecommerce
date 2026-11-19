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
        Schema::table('discount_cards', function (Blueprint $table) {
            $table->timestamp('document_reviewed_at')->nullable();
            $table->foreignUlid('document_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('document_reviewed_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discount_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_reviewed_tenant_id');
            $table->dropConstrainedForeignId('document_reviewed_by');
            $table->dropColumn('document_reviewed_at');
        });
    }
};
