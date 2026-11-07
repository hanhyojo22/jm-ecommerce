<?php

namespace App\Notifications;

use App\Support\WebPushAvailability;
use App\Support\WebPushPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ReturnCaseUpdated extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $returnCaseId,
        private readonly string $title,
        private readonly string $message,
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
            'type' => 'return_case',
            'return_case_id' => $this->returnCaseId,
            'title' => $this->title,
            'body' => $this->message,
        ];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        $tag = 'return-case-'.$this->returnCaseId;

        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->message)
            ->icon(asset('images/notification-icon.png'))
            ->badge(asset('images/notification-badge.png'))
            ->tag($tag)
            ->renotify()
            ->data(WebPushPayload::for($notification, $this->url, $tag));
    }
}
