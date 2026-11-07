<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class NotificationBell extends Component
{
    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function goTo(string $id): mixed
    {
        $notification = auth()->user()->notifications()->whereKey($id)->first();

        if ($notification === null) {
            return null;
        }

        $notification->markAsRead();

        return match ($notification->data['type'] ?? null) {
            'chat' => $this->redirect(route('messages.show', $notification->data['conversation_id'])),
            'order' => $this->redirect(route('orders.show', $notification->data['order_id'])),
            'seller_order' => $this->redirect(route('seller.orders.show', $notification->data['seller_order_id'])),
            default => null,
        };
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.notification-bell', [
            'unreadCount' => $user->unreadNotifications()->count(),
            'notifications' => $user->notifications()->latest()->limit(10)->get(),
        ]);
    }
}
