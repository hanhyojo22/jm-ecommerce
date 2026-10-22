<?php

namespace App\Livewire\Chat;

use App\Models\Conversation;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Messages · JMMart')]
class Inbox extends Component
{
    use WithPagination;

    #[Url]
    public string $filter = 'all';

    #[Url]
    public string $search = '';

    #[Url]
    public string $context = 'selling';

    public bool $embedded = false;

    public ?string $activeConversationId = null;

    /**
     * Livewire overrides the application's default pagination views for the duration of a
     * component render, so the shared marketplace pager has to be named here as well.
     */
    public function paginationSimpleView(): string
    {
        return 'components.marketplace.pagination';
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $user = auth()->user();
        abort_unless($user?->can('viewAny', Conversation::class), 403);

        $conversations = Conversation::query()
            ->with([
                'buyer',
                'store',
                'product',
                'sellerOrder.order',
                'messages' => fn ($query) => $query->orderByDesc('created_at')->orderByDesc('id')->limit(1),
                'readStates' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->withCount(['messages as unread_messages_count' => fn ($query) => Conversation::constrainToUnread($query, $user)])
            ->visibleTo($user)
            ->when($user->account_type === 'seller' && $this->context === 'buying', fn ($query) => $query->where('buyer_id', $user->id))
            ->when($user->account_type === 'seller' && $this->context !== 'buying', fn ($query) => $query->where('buyer_id', '!=', $user->id))
            ->when($this->filter === 'product', fn ($query) => $query->whereNotNull('product_id'))
            ->when($this->filter === 'order', fn ($query) => $query->whereNotNull('seller_order_id'))
            ->when($this->filter === 'unread', fn ($query) => $query->unreadFor($user))
            ->when(filled($this->search), function ($query): void {
                $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($this->search))).'%';
                $query->where(function ($query) use ($term): void {
                    $query->whereHas('buyer', fn ($buyer) => $buyer->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term]))
                        ->orWhereHas('store', fn ($store) => $store->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term]))
                        ->orWhereHas('product', fn ($product) => $product->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term]))
                        ->orWhereHas('sellerOrder.order', fn ($order) => $order->whereRaw("LOWER(number) LIKE ? ESCAPE '!'", [$term]));
                });
            })
            ->orderByDesc('latest_message_at')
            ->orderByDesc('id')
            ->simplePaginate(20);

        return view('livewire.chat.inbox', compact('conversations'));
    }
}
