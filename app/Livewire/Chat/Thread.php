<?php

namespace App\Livewire\Chat;

use App\Actions\SendConversationMessage;
use App\Events\ConversationInboxChanged;
use App\Events\ConversationReceiptUpdated;
use App\Events\TypingStatusChanged;
use App\Models\Conversation;
use App\Models\ConversationReadState;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Conversation · JMMart')]
class Thread extends Component
{
    private const INITIAL_MESSAGE_LIMIT = 50;

    private const WINDOW_MESSAGE_LIMIT = 200;

    public Conversation $conversation;

    public string $body = '';

    public string $clientMessageId = '';

    public bool $viewingHistory = false;

    /** @var array<int, string> */
    public array $messageIds = [];

    public function mount(Conversation $conversation): void
    {
        $conversation->load(['buyer', 'store', 'product.images', 'sellerOrder.order']);
        Gate::authorize('view', $conversation);
        $this->conversation = $conversation;
        $this->clientMessageId = (string) Str::uuid();
        $this->messageIds = $conversation->messages()
            ->latest('created_at')
            ->latest('id')
            ->limit(self::INITIAL_MESSAGE_LIMIT)
            ->pluck('id')
            ->reverse()
            ->values()
            ->all();
        $this->markRead();
    }

    /** @return array{id: string, client_message_id: string}|null */
    public function send(SendConversationMessage $sendMessage, ?string $draft = null, ?string $clientId = null): ?array
    {
        Gate::authorize('send', $this->conversation);
        if ($draft !== null) {
            $this->body = $draft;
        }
        if ($clientId !== null) {
            $this->clientMessageId = $clientId;
        }
        $validated = $this->validate([
            'body' => ['required', 'string', 'max:2000'],
            'clientMessageId' => ['required', 'uuid'],
        ]);
        $body = trim($validated['body']);
        $user = auth()->user();
        $key = 'conversation-message:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->addError('body', 'Please wait a moment before sending another message.');

            return null;
        }

        $message = $sendMessage->handle($this->conversation, $user, $body, $this->clientMessageId);
        if ($message->wasRecentlyCreated) {
            RateLimiter::hit($key, 60);
        }
        $this->appendMessageId($message->id);
        if ($this->viewingHistory) {
            $this->jumpToLatest();
        }
        $this->reset('body');
        $this->clientMessageId = (string) Str::uuid();
        $this->markRead();

        return ['id' => $message->id, 'client_message_id' => $message->client_message_id];
    }

    #[Renderless]
    public function startTyping(): void
    {
        Gate::authorize('send', $this->conversation);
        $user = auth()->user();
        $eventKey = 'conversation-typing:'.$this->conversation->id.':'.$user->id;
        RateLimiter::attempt($eventKey, 1, fn () => TypingStatusChanged::dispatch($this->conversation->id, $user->id, true), 2);
    }

    public function loadOlderMessages(): void
    {
        Gate::authorize('view', $this->conversation);
        $this->messageIds = array_slice(array_values(array_unique($this->messageIds)), -self::WINDOW_MESSAGE_LIMIT);
        $oldestMessage = $this->conversation->messages()->find($this->messageIds[0] ?? null);

        if ($oldestMessage === null) {
            return;
        }

        $olderMessages = $this->conversation->messages()
            ->where(function ($query) use ($oldestMessage): void {
                $query->where('created_at', '<', $oldestMessage->created_at)
                    ->orWhere(fn ($query) => $query->where('created_at', $oldestMessage->created_at)->where('id', '<', $oldestMessage->id));
            })
            ->latest('created_at')
            ->latest('id')
            ->limit(self::INITIAL_MESSAGE_LIMIT)
            ->pluck('id')
            ->reverse()
            ->values()
            ->all();

        $this->messageIds = array_slice([...$olderMessages, ...$this->messageIds], 0, self::WINDOW_MESSAGE_LIMIT);
        $this->viewingHistory = $this->hasNewerMessages();
    }

    public function loadNewerMessages(): void
    {
        Gate::authorize('view', $this->conversation);
        $newestMessage = $this->conversation->messages()->find($this->messageIds[array_key_last($this->messageIds)] ?? null);
        if ($newestMessage === null) {
            return;
        }

        $newerMessages = $this->conversation->messages()
            ->where(function ($query) use ($newestMessage): void {
                $query->where('created_at', '>', $newestMessage->created_at)
                    ->orWhere(fn ($query) => $query->where('created_at', $newestMessage->created_at)->where('id', '>', $newestMessage->id));
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::INITIAL_MESSAGE_LIMIT)
            ->pluck('id')->all();

        $this->messageIds = array_slice([...$this->messageIds, ...$newerMessages], -self::WINDOW_MESSAGE_LIMIT);
        $this->viewingHistory = $this->hasNewerMessages();
    }

    public function jumpToLatest(): void
    {
        Gate::authorize('view', $this->conversation);
        $this->messageIds = $this->conversation->messages()
            ->latest('created_at')->latest('id')
            ->limit(self::INITIAL_MESSAGE_LIMIT)
            ->pluck('id')->reverse()->values()->all();
        $this->viewingHistory = false;
    }

    /**
     * Advance this user's delivery and read receipts to the newest incoming message.
     */
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
        if (! $this->viewingHistory) {
            $this->appendMessageId($latestIncomingMessage->id);
        }

        $state = ConversationReadState::query()->firstOrCreate([
            'conversation_id' => $this->conversation->id,
            'user_id' => auth()->id(),
        ]);
        $receipts = [];

        if ($state->last_delivered_message_id !== $latestIncomingMessage->id) {
            $state->forceFill(['last_delivered_message_id' => $latestIncomingMessage->id, 'last_delivered_at' => now()]);
            $receipts[] = 'message.delivered';
        }

        if ($state->last_read_message_id !== $latestIncomingMessage->id) {
            $state->forceFill(['last_read_message_id' => $latestIncomingMessage->id, 'last_read_at' => now()]);
            $receipts[] = 'message.read';
        }

        if ($receipts === []) {
            return;
        }

        $state->save();

        foreach ($receipts as $receipt) {
            ConversationReceiptUpdated::dispatch($state, $receipt, $latestIncomingMessage->id);
        }

        if (in_array('message.read', $receipts, true)) {
            ConversationInboxChanged::dispatch($this->conversation, (string) auth()->id());
        }
    }

    public function syncLatestMessages(): void
    {
        Gate::authorize('view', $this->conversation);
        if ($this->viewingHistory) {
            return;
        }
        $latestMessageIds = $this->conversation->messages()
            ->latest('created_at')
            ->latest('id')
            ->limit(self::INITIAL_MESSAGE_LIMIT)
            ->pluck('id')
            ->reverse()
            ->values()
            ->all();
        $this->messageIds = array_slice(array_values(array_unique([...$this->messageIds, ...$latestMessageIds])), -self::WINDOW_MESSAGE_LIMIT);
    }

    private function appendMessageId(string $messageId): void
    {
        $this->messageIds = array_slice([...$this->messageIds, $messageId], -self::WINDOW_MESSAGE_LIMIT);
    }

    public function render(): View
    {
        Gate::authorize('view', $this->conversation);

        $this->messageIds = array_slice(array_values(array_unique($this->messageIds)), -self::WINDOW_MESSAGE_LIMIT);
        $messages = $this->conversation->messages()
            ->whereKey($this->messageIds)
            ->with(['sender', 'attachments'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $oldestMessage = $messages->first();
        $hasMoreMessages = $oldestMessage !== null && $this->conversation->messages()
            ->where(function ($query) use ($oldestMessage): void {
                $query->where('created_at', '<', $oldestMessage->created_at)
                    ->orWhere(fn ($query) => $query->where('created_at', $oldestMessage->created_at)->where('id', '<', $oldestMessage->id));
            })
            ->exists();
        $hasNewerMessages = $this->hasNewerMessages();

        $lastMessage = $messages->last();
        $recipientReadState = $lastMessage?->sender_id === auth()->id()
            ? $this->conversation->readStates()
                ->where('user_id', '!=', auth()->id())
                ->latest('last_read_at')
                ->first()
            : null;
        $receipt = $recipientReadState?->last_read_message_id === $lastMessage?->id
            ? 'Read'
            : ($recipientReadState?->last_delivered_message_id === $lastMessage?->id ? 'Delivered' : 'Sent');

        $canSend = Gate::allows('send', $this->conversation);

        return view('livewire.chat.thread', [
            'messages' => $messages,
            'hasMoreMessages' => $hasMoreMessages,
            'hasNewerMessages' => $hasNewerMessages,
            'lastMessage' => $lastMessage,
            'receipt' => $receipt,
            'canSend' => $canSend,
            'canUnblock' => ! $canSend && $this->conversation->blocksCreatedBy(auth()->user())->exists(),
        ]);
    }

    private function hasNewerMessages(): bool
    {
        $newestMessage = $this->conversation->messages()->find($this->messageIds[array_key_last($this->messageIds)] ?? null);
        if ($newestMessage === null) {
            return false;
        }

        return $this->conversation->messages()
            ->where(function ($query) use ($newestMessage): void {
                $query->where('created_at', '>', $newestMessage->created_at)
                    ->orWhere(fn ($query) => $query->where('created_at', $newestMessage->created_at)->where('id', '>', $newestMessage->id));
            })->exists();
    }
}
