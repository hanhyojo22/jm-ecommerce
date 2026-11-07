<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

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
        return ['database', 'broadcast'];
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
}
