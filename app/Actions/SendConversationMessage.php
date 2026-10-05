<?php

namespace App\Actions;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SendConversationMessage
{
    public function handle(Conversation $conversation, User $sender, string $body, string $clientMessageId, string $type = 'text'): ConversationMessage
    {
        $message = DB::transaction(function () use ($conversation, $sender, $body, $clientMessageId, $type): ConversationMessage {
            $message = ConversationMessage::query()->createOrFirst([
                'sender_id' => $sender->id,
                'client_message_id' => $clientMessageId,
            ], [
                'conversation_id' => $conversation->id,
                'type' => $type,
                'body' => trim($body),
            ]);

            if ($message->conversation_id !== $conversation->id || $message->body !== trim($body) || $message->type !== $type) {
                throw ValidationException::withMessages([
                    'clientMessageId' => 'This message identifier has already been used.',
                ]);
            }

            if ($message->wasRecentlyCreated) {
                Conversation::query()
                    ->whereKey($conversation->id)
                    ->where(fn ($query) => $query->whereNull('latest_message_at')->orWhere('latest_message_at', '<', $message->created_at))
                    ->update(['latest_message_at' => $message->created_at]);
            }

            return $message;
        });

        if ($message->wasRecentlyCreated) {
            MessageSent::dispatch($message);
        }

        return $message;
    }
}
