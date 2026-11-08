<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Models\User;
use App\Notifications\ChatMessageReceived;
use App\Services\PushNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
use Throwable;

class SendChatMessagePush implements ShouldQueue
{
    /**
     * This job is not idempotent: a retry re-notifies every recipient, since nothing
     * here records which recipients already got a push or database notification. A
     * timeout must not become a retry, so tries stays at 1 and the timeout is sized
     * to fit several recipients at web-push's own 10s HTTP timeout each.
     */
    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(private readonly PushNotifier $notifier) {}

    public function handle(MessageSent $event): void
    {
        $message = $event->message->loadMissing(['conversation.store', 'sender']);
        $conversation = $message->conversation;

        // Mirrors ConversationPolicy::isActiveStoreMember (via ChecksTenantMembership)
        // exactly: a deactivated staff member, or staff of a suspended store, can no
        // longer view the conversation through the API and must not be pushed its
        // content either.
        $sentByBuyer = $message->sender_id === $conversation->buyer_id;

        // Buyers talk to the store, not to whichever staff member replied.
        $senderName = $sentByBuyer ? $message->sender->name : $conversation->store->name;

        $recipients = $sentByBuyer
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
                $senderName,
                Str::limit($message->body, 120),
                ['type' => 'chat', 'conversation_id' => $conversation->id],
            );

            try {
                $recipient->notify(new ChatMessageReceived($senderName, $message->body, $conversation->id));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
