<?php

namespace App\Actions;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SendConversationMessage
{
    public function handle(Conversation $conversation, User $sender, string $body): ConversationMessage
    {
        $message = DB::transaction(function () use ($conversation, $sender, $body): ConversationMessage {
            $message = $conversation->messages()->create([
                'sender_id' => $sender->id,
                'body' => trim($body),
            ]);

            $conversation->forceFill(['latest_message_at' => $message->created_at])->saveQuietly();

            return $message;
        });

        MessageSent::dispatch($message);

        return $message;
    }
}
