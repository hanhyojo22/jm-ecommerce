<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TypingStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $conversationId, public string $userId, public bool $typing) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.'.$this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'conversation.typing';
    }

    /** @return array{event: string, conversation_id: string, user_id: string, typing: bool} */
    public function broadcastWith(): array
    {
        return ['event' => 'conversation.typing', 'conversation_id' => $this->conversationId, 'user_id' => $this->userId, 'typing' => $this->typing];
    }
}
