<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\TenantMember;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationInboxChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Conversation $conversation, public ?string $onlyUserId = null) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        if ($this->onlyUserId !== null) {
            return [new PrivateChannel('chat.inbox.'.$this->onlyUserId)];
        }

        $recipientIds = [$this->conversation->buyer_id];
        $this->conversation->loadMissing('store.tenant');

        if ($this->conversation->store->status === 'active' && $this->conversation->store->tenant->status === 'active') {
            $recipientIds = array_merge($recipientIds, TenantMember::query()
                ->where('tenant_id', $this->conversation->store->tenant_id)
                ->whereIn('role', ['owner', 'manager', 'staff'])
                ->whereHas('user', fn ($query) => $query->where('account_type', 'seller')->where('is_active', true))
                ->pluck('user_id')->all());
        }

        return array_map(fn (string $id): PrivateChannel => new PrivateChannel('chat.inbox.'.$id), array_unique($recipientIds));
    }

    public function broadcastAs(): string
    {
        return 'inbox.changed';
    }

    /** @return array{conversation_id: string} */
    public function broadcastWith(): array
    {
        return ['conversation_id' => $this->conversation->id];
    }
}
