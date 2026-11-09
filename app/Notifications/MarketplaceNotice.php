<?php

namespace App\Notifications;

use App\Support\NotificationIcon;
use App\Support\WebPushAvailability;
use App\Support\WebPushPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * A plain title-and-body notice that opens a URL: admin announcements and
 * cart reminders.
 */
class MarketplaceNotice extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $type,
        private readonly string $title,
        private readonly string $body,
        private readonly string $url,
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
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        $tag = $this->type.'-'.$notification->id;

        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon(NotificationIcon::url($this->type))
            ->badge(NotificationIcon::badgeUrl($this->type))
            ->tag($tag)
            ->data(WebPushPayload::for($notification, $this->url, $tag));
    }
}
