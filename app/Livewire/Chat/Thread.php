<?php

namespace App\Livewire\Chat;

use App\Actions\SendConversationMessage;
use App\Models\Conversation;
use App\Models\ConversationReadState;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class Thread extends Component
{
    private const INITIAL_MESSAGE_LIMIT = 50;

    private const MAX_MESSAGE_LIMIT = 200;

    public Conversation $conversation;

    public string $body = '';

    public int $messageLimit = self::INITIAL_MESSAGE_LIMIT;

    public function mount(Conversation $conversation): void
    {
        Gate::authorize('view', $conversation);
        $this->conversation = $conversation->load(['buyer', 'store', 'product.images', 'sellerOrder.order']);
        $this->markRead();
    }

    public function send(SendConversationMessage $sendMessage): void
    {
        Gate::authorize('send', $this->conversation);
        $validated = $this->validate(['body' => ['required', 'string', 'max:2000']]);
        $body = trim($validated['body']);

        if ($body === '') {
            $this->addError('body', 'A message cannot be empty.');

            return;
        }

        $key = 'conversation-message:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->addError('body', 'Please wait a moment before sending another message.');

            return;
        }

        RateLimiter::hit($key, 60);
        $sendMessage->handle($this->conversation, auth()->user(), $body);
        $this->reset('body');
        $this->markRead();
    }

    public function useQuickReply(string $reply): void
    {
        Gate::authorize('send', $this->conversation);
        $this->body = $reply;
    }

    public function loadOlderMessages(): void
    {
        Gate::authorize('view', $this->conversation);
        $this->messageLimit = min($this->messageLimit + self::INITIAL_MESSAGE_LIMIT, self::MAX_MESSAGE_LIMIT);
    }

    public function markRead(): void
    {
        $latestMessageId = $this->conversation->messages()->orderByDesc('created_at')->orderByDesc('id')->value('id');
        if ($latestMessageId === null) {
            return;
        }

        ConversationReadState::updateOrCreate([
            'conversation_id' => $this->conversation->id,
            'user_id' => auth()->id(),
        ], [
            'last_read_message_id' => $latestMessageId,
            'last_read_at' => now(),
        ]);
    }

    public function render(): View
    {
        Gate::authorize('view', $this->conversation);

        $messageLimit = min(max($this->messageLimit, self::INITIAL_MESSAGE_LIMIT), self::MAX_MESSAGE_LIMIT);
        $messageQuery = $this->conversation->messages()->with('sender')->latest('created_at')->latest('id');
        $hasMoreMessages = (clone $messageQuery)->count() > $messageLimit;

        return view('livewire.chat.thread', [
            'messages' => $messageQuery->limit($messageLimit)->get()->sortBy([
                ['created_at', 'asc'],
                ['id', 'asc'],
            ])->values(),
            'hasMoreMessages' => $hasMoreMessages,
        ]);
    }
}
