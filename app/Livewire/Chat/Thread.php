<?php

namespace App\Livewire\Chat;

use App\Actions\SendConversationMessage;
use App\Events\ConversationReceiptUpdated;
use App\Events\TypingStatusChanged;
use App\Models\Conversation;
use App\Models\ConversationReadState;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Title('Conversation · JMMart')]
class Thread extends Component
{
    private const INITIAL_MESSAGE_LIMIT = 50;

    private const MAX_MESSAGE_LIMIT = 200;

    public Conversation $conversation;

    public string $body = '';

    public string $clientMessageId = '';

    public int $messageLimit = self::INITIAL_MESSAGE_LIMIT;

    /** @var array<int, string> */
    public array $messageIds = [];

    public function mount(Conversation $conversation): void
    {
        Gate::authorize('view', $conversation);
        $this->conversation = $conversation->load(['buyer', 'store', 'product.images', 'sellerOrder.order']);
        $this->clientMessageId = (string) Str::uuid();
        $this->messageIds = $conversation->messages()
            ->latest('created_at')
            ->latest('id')
            ->limit(self::INITIAL_MESSAGE_LIMIT)
            ->pluck('id')
            ->reverse()
            ->values()
            ->all();
        $this->messageLimit = count($this->messageIds);
        $this->markDelivered();
        $this->markRead();
    }

    public function send(SendConversationMessage $sendMessage): void
    {
        Gate::authorize('send', $this->conversation);
        $validated = $this->validate([
            'body' => ['required', 'string', 'max:2000'],
            'clientMessageId' => ['required', 'uuid'],
        ]);
        $body = trim($validated['body']);

        if ($body === '') {
            $this->addError('body', 'A message cannot be empty.');

            return;
        }

        $user = auth()->user();
        $isRetry = $this->conversation->messages()
            ->where('sender_id', $user->id)
            ->where('client_message_id', $this->clientMessageId)
            ->exists();
        $key = 'conversation-message:'.$user->id;
        if (! $isRetry && RateLimiter::tooManyAttempts($key, 20)) {
            $this->addError('body', 'Please wait a moment before sending another message.');

            return;
        }

        $message = $sendMessage->handle($this->conversation, $user, $body, $this->clientMessageId);
        if ($message->wasRecentlyCreated) {
            RateLimiter::hit($key, 60);
        }
        $this->appendMessageId($message->id);
        $this->reset('body');
        $this->clientMessageId = (string) Str::uuid();
        $this->markRead();
    }

    public function startTyping(): void
    {
        Gate::authorize('send', $this->conversation);
        $user = auth()->user();
        $key = 'chat:typing:'.$this->conversation->id.':'.$user->id;

        try {
            Cache::store('redis')->put($key, true, now()->addSeconds(5));
        } catch (Throwable $exception) {
            Log::warning('Chat typing state is unavailable.', ['conversation_id' => $this->conversation->id, 'user_id' => $user->id, 'exception' => $exception::class]);
        }

        $eventKey = 'conversation-typing:'.$this->conversation->id.':'.$user->id;
        if (RateLimiter::attempt($eventKey, 1, fn () => TypingStatusChanged::dispatch($this->conversation->id, $user->id, true), 2)) {
            return;
        }
    }

    public function loadOlderMessages(): void
    {
        Gate::authorize('view', $this->conversation);
        $this->messageIds = array_slice(array_values(array_unique($this->messageIds)), -self::MAX_MESSAGE_LIMIT);
        $oldestMessage = $this->conversation->messages()->find($this->messageIds[0] ?? null);
        $remainingLimit = self::MAX_MESSAGE_LIMIT - count($this->messageIds);

        if ($oldestMessage === null || $remainingLimit === 0) {
            $this->messageLimit = count($this->messageIds);

            return;
        }

        $olderMessages = $this->conversation->messages()
            ->where(function ($query) use ($oldestMessage): void {
                $query->where('created_at', '<', $oldestMessage->created_at)
                    ->orWhere(fn ($query) => $query->where('created_at', $oldestMessage->created_at)->where('id', '<', $oldestMessage->id));
            })
            ->latest('created_at')
            ->latest('id')
            ->limit(min(self::INITIAL_MESSAGE_LIMIT, $remainingLimit))
            ->pluck('id')
            ->reverse()
            ->values()
            ->all();

        $this->messageIds = [...$olderMessages, ...$this->messageIds];
        $this->messageLimit = count($this->messageIds);
    }

    public function markRead(): void
    {
        Gate::authorize('view', $this->conversation);

        $latestIncomingMessage = $this->conversation->messages()
            ->where('sender_id', '!=', auth()->id())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
        if ($latestIncomingMessage === null) {
            return;
        }
        $this->appendMessageId($latestIncomingMessage->id);

        $state = ConversationReadState::query()->firstOrCreate([
            'conversation_id' => $this->conversation->id,
            'user_id' => auth()->id(),
        ]);
        if ($state->last_read_message_id === $latestIncomingMessage->id) {
            return;
        }

        $state->forceFill([
            'last_read_message_id' => $latestIncomingMessage->id,
            'last_read_at' => now(),
        ])->save();
        ConversationReceiptUpdated::dispatch($state, 'message.read', $latestIncomingMessage->id);
    }

    public function markDelivered(): void
    {
        Gate::authorize('view', $this->conversation);
        $latestIncomingMessage = $this->conversation->messages()
            ->where('sender_id', '!=', auth()->id())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($latestIncomingMessage === null) {
            return;
        }

        $state = ConversationReadState::query()->firstOrCreate([
            'conversation_id' => $this->conversation->id,
            'user_id' => auth()->id(),
        ]);
        if ($state->last_delivered_message_id === $latestIncomingMessage->id) {
            return;
        }

        $state->forceFill(['last_delivered_message_id' => $latestIncomingMessage->id, 'last_delivered_at' => now()])->save();
        ConversationReceiptUpdated::dispatch($state, 'message.delivered', $latestIncomingMessage->id);
    }

    public function syncLatestMessages(): void
    {
        Gate::authorize('view', $this->conversation);
        $latestMessageIds = $this->conversation->messages()
            ->latest('created_at')
            ->latest('id')
            ->limit(self::MAX_MESSAGE_LIMIT)
            ->pluck('id')
            ->reverse()
            ->values()
            ->all();
        $this->messageIds = array_slice(array_values(array_unique([...$this->messageIds, ...$latestMessageIds])), -self::MAX_MESSAGE_LIMIT);
        $this->messageLimit = count($this->messageIds);
    }

    private function appendMessageId(string $messageId): void
    {
        $this->messageIds = array_slice([...$this->messageIds, $messageId], -self::MAX_MESSAGE_LIMIT);
        $this->messageLimit = count($this->messageIds);
    }

    public function render(): View
    {
        Gate::authorize('view', $this->conversation);

        $this->messageIds = array_slice(array_values(array_unique($this->messageIds)), -self::MAX_MESSAGE_LIMIT);
        $messages = $this->conversation->messages()
            ->whereKey($this->messageIds)
            ->with(['sender', 'attachments'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $this->messageLimit = count($this->messageIds);
        $oldestMessage = $messages->first();
        $hasMoreMessages = $oldestMessage !== null && $this->messageLimit < self::MAX_MESSAGE_LIMIT && $this->conversation->messages()
            ->where(function ($query) use ($oldestMessage): void {
                $query->where('created_at', '<', $oldestMessage->created_at)
                    ->orWhere(fn ($query) => $query->where('created_at', $oldestMessage->created_at)->where('id', '<', $oldestMessage->id));
            })
            ->exists();

        $lastMessage = $messages->last();
        $recipientReadState = $lastMessage?->sender_id === auth()->id()
            ? $this->conversation->readStates()
                ->where('user_id', '!=', auth()->id())
                ->whereHas('user', fn ($query) => $query->where('account_type', auth()->user()->account_type === 'buyer' ? 'seller' : 'buyer'))
                ->latest('last_read_at')
                ->first()
            : null;
        $receipt = $recipientReadState?->last_read_message_id === $lastMessage?->id
            ? 'Read'
            : ($recipientReadState?->last_delivered_message_id === $lastMessage?->id ? 'Delivered' : 'Sent');

        return view('livewire.chat.thread', [
            'messages' => $messages,
            'hasMoreMessages' => $hasMoreMessages,
            'lastMessage' => $lastMessage,
            'receipt' => $receipt,
        ]);
    }
}
