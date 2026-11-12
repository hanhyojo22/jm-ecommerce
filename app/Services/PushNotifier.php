<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use App\Support\NotificationIcon;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use RuntimeException;
use Throwable;

class PushNotifier
{
    private readonly ?Messaging $messaging;

    public function __construct(?Messaging $messaging = null)
    {
        // This can run inline on a request (sync/deferred queues) or inside a
        // queue worker. A missing or malformed Firebase credential must never
        // break either path, so a null client silently disables mobile pushes.
        $this->messaging = $messaging ?? $this->buildMessaging();
    }

    private function buildMessaging(): ?Messaging
    {
        $credentials = config('services.fcm.credentials');

        if (empty($credentials)) {
            return null;
        }

        // Render supplies the service account as raw JSON in an env var;
        // local dev points at a file on disk instead.
        $value = is_string($credentials) && str_starts_with(trim($credentials), '{')
            ? json_decode($credentials, true)
            : $credentials;

        if ($value === null) {
            report(new RuntimeException('FIREBASE_CREDENTIALS is set but is not valid JSON or a readable file path.'));

            return null;
        }

        try {
            return (new Factory)->withServiceAccount($value)->createMessaging();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Pushes to every device the user is signed in on. Silently does nothing
     * for a user with no registered device, or while push is unconfigured.
     *
     * @param  array<string, string>  $data
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        if ($this->messaging === null) {
            return;
        }

        $devices = $user->deviceTokens()->get();

        if ($devices->isEmpty()) {
            return;
        }

        foreach ($devices->groupBy(fn (DeviceToken $device): int => (int) ($device->platform === 'android' && $device->rich_notifications)) as $rich => $group) {
            $message = $this->buildMessage($title, $body, $data, (bool) $rich);
            try {
                $report = $this->messaging->sendMulticast($message, $group->pluck('token')->all());
            } catch (Throwable $e) {
                report($e);

                continue;
            }

            $dead = [...$report->invalidTokens(), ...$report->unknownTokens()];
            if ($dead !== []) {
                DeviceToken::query()->whereIn('token', $dead)->delete();
            }
        }
    }

    /**
     * Chats go to the mobile app's "Messages" channel and replace the previous
     * notification for the same conversation; announcements and cart reminders
     * go to "Updates and offers"; everything else goes to "Orders".
     * Channel IDs must match the ones created in mobile push_notifications.dart.
     *
     * @param  array<string, string>  $data
     */
    public function buildMessage(string $title, string $body, array $data = [], bool $rich = false): CloudMessage
    {
        $type = $data['type'] ?? null;
        $isChat = $type === 'chat';

        $androidNotification = array_filter([
            'channel_id' => match ($type) {
                'chat' => 'chat_messages',
                // Kept apart so muting offers never silences order updates.
                'announcement', 'cart' => 'marketplace_updates',
                default => 'order_updates',
            },
            'tag' => $isChat && isset($data['conversation_id']) ? 'conversation-'.$data['conversation_id'] : ($type === 'order' && isset($data['order_id']) ? 'order-'.$data['order_id'] : null),
            // An app build without this drawable falls back to the manifest's default icon.
            'icon' => ($icon = NotificationIcon::key($type, $data['status'] ?? null)) !== null ? 'ic_stat_'.$icon : null,
        ]);

        $icon = $androidNotification['icon'] ?? 'ic_stat_notification';
        if ($rich) {
            // Updated Android installs render one native card in either app state.
            return CloudMessage::new()->withData($data + [
                'title' => $title,
                'body' => $body,
                'small_icon' => $icon,
                'rich_notification' => '1',
            ])->withAndroidConfig(['priority' => 'high', 'ttl' => $isChat ? '86400s' : '3600s']);
        }

        $image = $data['image_url'] ?? $data['avatar_url'] ?? null;
        $notification = array_filter(['title' => $title, 'body' => $body, 'image' => $image]);

        return CloudMessage::new()->withNotification($notification)->withData($data)
            ->withAndroidConfig(['priority' => 'high', 'notification' => $androidNotification]);
    }
}
