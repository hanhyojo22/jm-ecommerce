<?php

namespace App\Support;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class WebPushPayload
{
    /**
     * The web-push channel JSON-encodes WebPushMessage::toArray(), which nests this
     * array under `data`. The service worker therefore reads payload.data.url,
     * payload.data.read_url, and so on.
     *
     * @return array{url: string, notification_id: string|null, read_url: string|null, tag: string}
     */
    public static function for(Notification $notification, string $url, string $tag): array
    {
        return [
            'url' => $url,
            'notification_id' => $notification->id,
            'read_url' => self::readUrl($notification->id),
            'tag' => $tag,
        ];
    }

    /**
     * A signed GET rather than an authenticated page request: the click may open a
     * fresh window whose Clerk session cookie has already expired, and marking the
     * notification read is harmless to repeat.
     */
    private static function readUrl(?string $notificationId): ?string
    {
        if (blank($notificationId)) {
            return null;
        }

        return URL::temporarySignedRoute(
            'push-notifications.read',
            now()->addDays(7),
            ['notification' => $notificationId],
        );
    }
}
