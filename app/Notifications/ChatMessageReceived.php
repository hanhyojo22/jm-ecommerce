<?php

namespace App\Notifications;

use App\Support\WebPushAvailability;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ChatMessageReceived extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $senderName,
        private readonly string $body,
        private readonly string $conversationId,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return WebPushAvailability::isConfigured()
            ? ['database', 'broadcast', WebPushChannel::class]
            : ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'chat',
            'conversation_id' => $this->conversationId,
            'title' => $this->senderName,
            'body' => Str::limit($this->body, 120),
        ];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->senderName)
            ->body(Str::limit($this->body, 120))
            ->icon('/favicon.svg')
            ->data(['url' => route('messages.show', $this->conversationId)]);
    }
}
