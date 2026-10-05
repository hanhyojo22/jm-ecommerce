<?php

namespace App\Events;

use App\Models\ConversationReadState;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationReceiptUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ConversationReadState $state, public string $event, public string $messageId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.'.$this->state->conversation_id)];
    }

    public function broadcastAs(): string
    {
        return $this->event;
    }

    /** @return array{event: string, conversation_id: string, message_id: string} */
    public function broadcastWith(): array
    {
        return [
            'event' => $this->event,
            'conversation_id' => $this->state->conversation_id,
            'message_id' => $this->messageId,
        ];
    }
}
