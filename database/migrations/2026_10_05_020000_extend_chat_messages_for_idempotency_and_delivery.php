<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table): void {
            $table->uuid('client_message_id')->nullable();
            $table->string('type', 32)->default('text');
            $table->foreignUlid('reply_to_id')->nullable()->constrained('conversation_messages')->nullOnDelete();
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unique(['sender_id', 'client_message_id']);
            $table->index(['conversation_id', 'created_at', 'id']);
        });

        Schema::table('conversation_read_states', function (Blueprint $table): void {
            $table->foreignUlid('last_delivered_message_id')->nullable()->constrained('conversation_messages')->nullOnDelete();
            $table->timestamp('last_delivered_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('conversation_read_states', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('last_delivered_message_id');
            $table->dropColumn('last_delivered_at');
        });

        Schema::table('conversation_messages', function (Blueprint $table): void {
            $table->dropUnique(['sender_id', 'client_message_id']);
            $table->dropIndex(['conversation_id', 'created_at', 'id']);
            $table->dropConstrainedForeignId('reply_to_id');
            $table->dropColumn(['client_message_id', 'type', 'edited_at', 'deleted_at']);
        });
    }
};
