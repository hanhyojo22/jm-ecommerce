<?php

namespace App\Actions;

use App\Events\ConversationInboxChanged;
use App\Events\ConversationReceiptUpdated;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationReadState;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MarkConversationRead
{
    /**
     * Advance this user's delivery and read receipts to the newest incoming message.
     *
     * Shared by the web thread and the app so a read receipt means the same thing
     * whichever client the buyer uses.
     *
     * @return array{message: ConversationMessage|null, receipts: list<string>}
     */
    public function handle(Conversation $conversation, User $user): array
    {
        $latestIncomingMessage = $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($latestIncomingMessage === null) {
            return ['message' => null, 'receipts' => []];
        }

        $unreadIds = $conversation->messages()->where('sender_id', '!=', $user->id)
            ->whereNotIn('id', DB::table('conversation_message_reads')->where('user_id', $user->id)->select('message_id'))
            ->pluck('id');
        foreach ($unreadIds as $messageId) {
            DB::table('conversation_message_reads')->insertOrIgnore(['message_id' => $messageId, 'user_id' => $user->id, 'read_at' => now()]);
        }

        $state = ConversationReadState::query()->firstOrCreate([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ]);
        $receipts = [];

        if ($state->last_delivered_message_id !== $latestIncomingMessage->id) {
            $state->forceFill(['last_delivered_message_id' => $latestIncomingMessage->id, 'last_delivered_at' => now()]);
            $receipts[] = 'message.delivered';
        }

        if ($state->last_read_message_id !== $latestIncomingMessage->id) {
            $state->forceFill(['last_read_message_id' => $latestIncomingMessage->id, 'last_read_at' => now()]);
            $receipts[] = 'message.read';
        }

        if ($receipts === []) {
            return ['message' => $latestIncomingMessage, 'receipts' => []];
        }

        $state->save();

        foreach ($receipts as $receipt) {
            ConversationReceiptUpdated::dispatch($state, $receipt, $latestIncomingMessage->id);
        }

        if ($unreadIds->isNotEmpty()) {
            ConversationInboxChanged::dispatch($conversation, (string) $user->id);
        }

        return ['message' => $latestIncomingMessage, 'receipts' => $receipts];
    }
}
