<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Notifications\ChatMessageReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->where('type', '!=', ChatMessageReceived::class);

        return response()->json([
            'data' => (clone $notifications)->latest()->limit(50)->get()->map(fn ($notification): array => [
                'id' => $notification->id,
                'type' => $notification->data['type'] ?? '',
                'title' => $notification->data['title'] ?? 'Notification',
                'body' => $notification->data['body'] ?? '',
                'order_id' => $notification->data['order_id'] ?? null,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at->toIso8601String(),
            ]),
            'unread_count' => (clone $notifications)->whereNull('read_at')->count(),
        ]);
    }

    public function markRead(Request $request, string $notification): Response
    {
        $request->user()->notifications()
            ->where('type', '!=', ChatMessageReceived::class)
            ->whereKey($notification)
            ->firstOrFail()
            ->markAsRead();

        return response()->noContent();
    }

    public function markAllRead(Request $request): Response
    {
        $request->user()->unreadNotifications()
            ->where('type', '!=', ChatMessageReceived::class)
            ->update(['read_at' => now()]);

        return response()->noContent();
    }
}
