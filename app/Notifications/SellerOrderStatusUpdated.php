<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SellerOrderStatusUpdated extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $orderId,
        private readonly string $status,
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
            'type' => 'order',
            'order_id' => $this->orderId,
            'status' => $this->status,
            'title' => $this->title,
            'body' => $this->message,
        ];
    }
}
