<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Models\User;
use App\Notifications\ChatMessageReceived;
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

        // Mirrors ConversationPolicy::isActiveStoreMember (via ChecksTenantMembership)
        // exactly: a deactivated staff member, or staff of a suspended store, can no
        // longer view the conversation through the API and must not be pushed its
        // content either.
        $recipients = $message->sender_id === $conversation->buyer_id
            ? User::query()
                ->where('account_type', 'seller')
                ->where('is_active', true)
                ->whereHas('tenantMemberships', fn ($query) => $query
                    ->where('tenant_id', $conversation->store->tenant_id)
                    ->whereIn('role', ['owner', 'manager', 'staff'])
                    ->whereHas('tenant', fn ($tenant) => $tenant
                        ->where('status', 'active')
                        ->whereHas('store', fn ($store) => $store->where('status', 'active'))))
                ->get()
            : collect([$conversation->buyer])->filter(fn (?User $user) => $user?->is_active === true);

        foreach ($recipients as $recipient) {
            $this->notifier->sendToUser(
                $recipient,
                $message->sender->name,
                Str::limit($message->body, 120),
                ['type' => 'chat', 'conversation_id' => $conversation->id],
            );

            $recipient->notify(new ChatMessageReceived($message->sender->name, $message->body, $conversation->id));
        }
    }
}
