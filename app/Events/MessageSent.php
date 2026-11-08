<?php

namespace App\Events;

use App\Models\ConversationMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/*
 * ShouldRescue: the message is already committed by the time this broadcasts, so a
 * down/sleeping Reverb must not turn a successful send into a request-failing 500.
 */
class MessageSent implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ConversationMessage $message) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->message->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    /** @return array{event: string, conversation_id: string, message: array{id: string, sender_id: string, client_message_id: ?string, type: string, body: string, created_at: string}} */
    public function broadcastWith(): array
    {
        return [
            'event' => 'message.created',
            'conversation_id' => $this->message->conversation_id,
            'message' => [
                'id' => $this->message->id,
                'sender_id' => $this->message->sender_id,
                'client_message_id' => $this->message->client_message_id,
                'type' => $this->message->type,
                'body' => $this->message->body,
                'created_at' => $this->message->created_at->toIso8601String(),
            ],
        ];
    }
}
