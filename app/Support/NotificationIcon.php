<?php

namespace App\Support;

/**
 * Picks the push icon for a notification from its data `type` (and an order's
 * `status`). The keys name the images in public/images/notifications and the
 * Android ic_stat_* drawables, and must match mobile push_notifications.dart.
 */
class NotificationIcon
{
    public static function key(?string $type, ?string $status = null): ?string
    {
        return match ($type) {
            'chat' => 'message',
            'seller_order' => 'new_order',
            'order' => match ($status) {
                'confirmed' => 'confirmed',
                'processing' => 'preparing',
                'shipped' => 'shipped',
                'delivered' => 'delivered',
                'cancelled' => 'cancelled',
                default => 'order',
            },
            'order_issue' => 'order_help',
            'return_case' => 'return',
            'announcement' => 'announcement',
            'cart' => 'cart',
            default => null,
        };
    }

    public static function url(?string $type, ?string $status = null): string
    {
        $key = self::key($type, $status);

        return asset($key === null ? 'images/notification-icon.png' : "images/notifications/{$key}.png");
    }

    public static function badgeUrl(?string $type, ?string $status = null): string
    {
        $key = self::key($type, $status);

        return asset($key === null ? 'images/notification-badge.png' : "images/notifications/{$key}-badge.png");
    }
}
