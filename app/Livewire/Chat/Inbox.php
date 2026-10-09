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
            ->visibleTo($user)
            ->when($this->filter === 'product', fn ($query) => $query->whereNotNull('product_id'))
            ->when($this->filter === 'order', fn ($query) => $query->whereNotNull('seller_order_id'))
            ->when($this->filter === 'unread', fn ($query) => $query->unreadFor($user))
            ->when(filled($this->search), function ($query): void {
                $term = '%'.mb_strtolower(trim($this->search)).'%';
                $query->where(function ($query) use ($term): void {
                    $query->whereHas('buyer', fn ($buyer) => $buyer->whereRaw('LOWER(name) LIKE ?', [$term]))
                        ->orWhereHas('store', fn ($store) => $store->whereRaw('LOWER(name) LIKE ?', [$term]))
                        ->orWhereHas('product', fn ($product) => $product->whereRaw('LOWER(name) LIKE ?', [$term]))
                        ->orWhereHas('sellerOrder.order', fn ($order) => $order->whereRaw('LOWER(number) LIKE ?', [$term]));
                });
            })
            ->orderByDesc('latest_message_at')
            ->orderByDesc('id')
            ->simplePaginate(20);

        return view('livewire.chat.inbox', compact('conversations'));
    }
}
