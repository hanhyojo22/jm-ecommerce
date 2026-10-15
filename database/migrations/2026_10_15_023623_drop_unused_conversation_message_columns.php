<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop groundwork columns for message editing, deletion, and replies. No feature
     * writes them, and `deleted_at` without a SoftDeletes model reads as a working
     * soft delete that hides nothing.
     */
    public function up(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reply_to_id');
            $table->dropColumn(['edited_at', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table): void {
            $table->foreignUlid('reply_to_id')->nullable()->constrained('conversation_messages')->nullOnDelete();
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }
};
