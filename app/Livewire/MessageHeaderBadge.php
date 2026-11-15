<?php

namespace App\Livewire;

use App\Models\Conversation;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class MessageHeaderBadge extends Component
{
    public bool $sellerWorkspace = false;

    public function render(): View
    {
        $user = auth()->user();
        $unreadCount = $user?->can('viewAny', Conversation::class)
            ? Conversation::query()
                ->visibleTo($user)
                ->when($this->sellerWorkspace,
                    fn ($query) => $query->where('buyer_id', '!=', $user->id),
                    fn ($query) => $query->where('buyer_id', $user->id))
                ->unreadFor($user)
                ->count()
            : 0;

        $messagesUrl = $this->sellerWorkspace
            ? route('messages.index', ['context' => 'selling'])
            : ($user?->account_type === 'seller'
                ? route('messages.index', ['context' => 'buying'])
                : route('messages.index'));

        return view('livewire.message-header-badge', compact('unreadCount', 'messagesUrl'));
    }
}
