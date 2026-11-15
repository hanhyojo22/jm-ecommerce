<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The pharmacy app has no buyer-seller chat, so its tables go (children first).
     */
    public function up(): void
    {
        foreach (['message_reports', 'message_attachments', 'conversation_message_reads', 'conversation_read_states', 'conversation_aliases', 'conversation_messages', 'conversations', 'blocked_users'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Chat is not coming back; restore an older backup if its data is ever needed.
     */
    public function down(): void {}
};
