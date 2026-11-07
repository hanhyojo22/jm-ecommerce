<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;

class PushNotificationController extends Controller
{
    /**
     * The URL is signed by the server and only appears inside the encrypted push
     * payload, so no session is required. That matters because the OS may open a
     * fresh window after the Clerk cookie has already expired.
     */
    public function markRead(string $notification): Response
    {
        DatabaseNotification::query()
            ->whereKey($notification)
            ->firstOrFail()
            ->markAsRead();

        return response()->noContent();
    }
}
