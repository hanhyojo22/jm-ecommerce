<?php

namespace App\Notifications;

use App\Support\WebPushAvailability;
use App\Support\WebPushPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

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
        return WebPushAvailability::isConfigured()
            ? ['database', 'broadcast', WebPushChannel::class]
            : ['database', 'broadcast'];
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

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        $tag = 'seller-order-'.$this->sellerOrderId;

        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->message)
            ->icon(asset('images/notification-icon.png'))
            ->badge(asset('images/notification-badge.png'))
            ->tag($tag)
            ->renotify()
            ->data(WebPushPayload::for($notification, route('seller.orders.show', $this->sellerOrderId), $tag));
    }
}
