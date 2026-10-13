<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('conversation.{conversation}', function ($user, Conversation $conversation): array|false {
    return $user->can('view', $conversation)
        ? ['id' => $user->id, 'name' => $user->name]
        : false;
});

Broadcast::channel('chat.inbox.{userId}', function ($user, string $userId): bool {
    return (string) $user->id === $userId && $user->can('viewAny', Conversation::class);
});
