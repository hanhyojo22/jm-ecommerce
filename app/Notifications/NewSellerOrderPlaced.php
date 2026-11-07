<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewSellerOrderPlaced extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $sellerOrderId,
        private readonly string $title,
        private readonly string $message,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'seller_order',
            'seller_order_id' => $this->sellerOrderId,
            'title' => $this->title,
            'body' => $this->message,
        ];
    }
}
