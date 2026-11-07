<?php

namespace App\Support;

class WebPushAvailability
{
    /**
     * A missing VAPID keypair must never break a notify() call — it just means no browser has
     * been asked to subscribe yet, or (locally) nobody has generated keys.
     */
    public static function isConfigured(): bool
    {
        return filled(config('webpush.vapid.public_key')) && filled(config('webpush.vapid.private_key'));
    }
}
