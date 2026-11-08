<?php

namespace App\Livewire\Chat;

use App\Actions\ConversationContext;
use App\Actions\MarkConversationRead;
use App\Actions\SendConversationMessage;
use App\Events\TypingStatusChanged;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
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

    public ?string $pendingProductId = null;

    public ?string $pendingSellerOrderId = null;

    public bool $viewingHistory = false;

    /** @var array<int, string> */
    public array $messageIds = [];

    public function mount(Conversation $conversation): void
    {
        $conversation->load(['buyer', 'store']);
        Gate::authorize('view', $conversation);
        $this->conversation = $conversation;
        if ($conversation->buyer_id === auth()->id()) {
            $this->pendingProductId = request()->query('product_id');
            $this->pendingSellerOrderId = request()->query('seller_order_id');
            app(ConversationContext::class)->forMessage($conversation, auth()->user(), $this->pendingProductId, $this->pendingSellerOrderId, false);
        }
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

        $message = $sendMessage->handle($this->conversation, $user, $body, $this->clientMessageId, 'text', $this->pendingProductId, $this->pendingSellerOrderId);
        if ($message->wasRecentlyCreated) {
            RateLimiter::hit($key, 60);
        }
        $this->appendMessageId($message->id);
        if ($this->viewingHistory) {
            $this->jumpToLatest();
        }
        $this->reset('body');
        $this->pendingProductId = null;
        $this->pendingSellerOrderId = null;
        $this->clientMessageId = (string) Str::uuid();
        $this->markRead();

        return ['id' => $message->id, 'client_message_id' => $message->client_message_id];
    }

    #[Renderless]
    public function startTyping(): void
    {
        $user = auth()->user();
        $eventKey = 'conversation-typing:'.$this->conversation->id.':'.$user->id;

        /*
         * Typing calls this every 500ms, and the limiter drops three of every four. Authorising
         * first meant each of those dropped calls still paid for a policy check, so the store and
         * the block list were read on every keystroke. Nothing is broadcast without the check; it
         * just happens where the work actually is.
         */
        RateLimiter::attempt($eventKey, 1, function () use ($user): void {
            Gate::authorize('send', $this->conversation);
            TypingStatusChanged::dispatch($this->conversation->id, $user->id, true);
        }, 2);
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

    public function clearPendingContext(): void
    {
        $this->pendingProductId = null;
        $this->pendingSellerOrderId = null;
    }

    /**
     * Advance this user's delivery and read receipts to the newest incoming message.
     */
    public function markRead(): void
    {
        Gate::authorize('view', $this->conversation);

        $result = app(MarkConversationRead::class)->handle($this->conversation, auth()->user());

        if ($result['message'] !== null && ! $this->viewingHistory) {
            $this->appendMessageId($result['message']->id);
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
        $lastMessage = $messages->last();
        // The window's last message is the one this would otherwise go and fetch again.
        $hasNewerMessages = $this->hasNewerMessages($lastMessage);
        $lastOutgoing = $messages->last(fn ($m) => $m->sender_id === auth()->id());
        // shortcut: no separate delivery event yet; Delivered is unreachable until a push-notification receipt endpoint exists
        $receipt = $lastOutgoing !== null && DB::table('conversation_message_reads')->where('message_id', $lastOutgoing->id)->where('user_id', '!=', auth()->id())->exists() ? 'Read' : 'Sent';

        $canSend = Gate::allows('send', $this->conversation);
        $pendingContext = ($this->pendingProductId !== null || $this->pendingSellerOrderId !== null)
            ? app(ConversationContext::class)->forMessage($this->conversation, auth()->user(), $this->pendingProductId, $this->pendingSellerOrderId, false)['context_snapshot']
            : null;
        // Falls back to the most recently loaded context-bearing message so the header still names
        // the inquiry after the buyer's first message scrolls out of the visible window.
        $headerContextType = $pendingContext['type']
            ?? $messages->last(fn ($m) => $m->context_snapshot !== null)?->context_snapshot['type']
            ?? null;
        $headerContextLabel = match ($headerContextType) {
            'product' => 'Product inquiry',
            'order' => 'Order conversation',
            default => 'Marketplace chat',
        };

        return view('livewire.chat.thread', [
            'messages' => $messages,
            'hasMoreMessages' => $hasMoreMessages,
            'hasNewerMessages' => $hasNewerMessages,
            'lastMessage' => $lastMessage,
            'lastOutgoing' => $lastOutgoing,
            'receipt' => $receipt,
            'canSend' => $canSend,
            'pendingContext' => $pendingContext,
            'headerContextLabel' => $headerContextLabel,
            'canUnblock' => ! $canSend && $this->conversation->blocksCreatedBy(auth()->user())->exists(),
        ]);
    }

    private function hasNewerMessages(?ConversationMessage $newestMessage = null): bool
    {
        $newestMessage ??= $this->conversation->messages()->find($this->messageIds[array_key_last($this->messageIds)] ?? null);
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
