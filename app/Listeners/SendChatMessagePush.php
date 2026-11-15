<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Models\User;
use App\Notifications\ChatMessageReceived;
use App\Services\PushNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;
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
        $message = $event->message->loadMissing(['conversation.store.tenant', 'sender']);
        $conversation = $message->conversation;

        // Mirrors who may open the chat: a deactivated staff member, staff of another
        // branch, or staff of a suspended store must not be pushed its content.
        $sentByBuyer = $message->sender_id === $conversation->buyer_id;

        // Buyers talk to the store, not to whichever staff member replied.
        $senderName = $sentByBuyer ? $message->sender->name : $conversation->store->name;
        $preview = filled($message->body) ? Str::limit($message->body, 120) : 'Sent an attachment';

        $recipients = $sentByBuyer
            // The chat's branch and head office, the same people who can open it.
            ? ($conversation->store->status === 'active'
                ? $conversation->store->tenant->activeNotifiableStaff($conversation->store_id)
                : collect())
            : collect([$conversation->buyer])->filter(fn (?User $user) => $user?->is_active === true);

        foreach ($recipients as $recipient) {
            $this->notifier->sendToUser(
                $recipient,
                $senderName,
                $preview,
                array_filter([
                    'type' => 'chat',
                    'conversation_id' => $conversation->id,
                    'sender_name' => $senderName,
                    'avatar_url' => ! $sentByBuyer && $conversation->store->logo_path !== null
                        ? Storage::disk('public')->url($conversation->store->logo_path) : null,
                ]),
            );

            try {
                $recipient->notify(new ChatMessageReceived($senderName, $message->body, $conversation->id));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
