<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;

class SendChatMessagePush implements ShouldQueue
{
    public function __construct(private readonly PushNotifier $notifier) {}

    public function handle(MessageSent $event): void
    {
        $message = $event->message->loadMissing(['conversation.store', 'sender']);
        $conversation = $message->conversation;

        $recipients = $message->sender_id === $conversation->buyer_id
            ? User::query()
                ->whereHas('tenantMemberships', fn ($query) => $query
                    ->where('tenant_id', $conversation->store->tenant_id)
                    ->whereIn('role', ['owner', 'manager', 'staff']))
                ->get()
            : collect([$conversation->buyer])->filter();

        foreach ($recipients as $recipient) {
            $this->notifier->sendToUser(
                $recipient,
                $message->sender->name,
                Str::limit($message->body, 120),
                ['type' => 'chat', 'conversation_id' => $conversation->id],
            );
        }
    }
}
