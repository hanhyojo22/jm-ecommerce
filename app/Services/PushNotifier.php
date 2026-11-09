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

    public function __construct()
    {
        // This can run inline on a request (sync/deferred queues) or inside a
        // queue worker. A missing or malformed Firebase credential must never
        // break either path, so a null client silently disables mobile pushes.
        $this->messaging = $this->buildMessaging();
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

        $tokens = $user->deviceTokens()->pluck('token')->all();

        if ($tokens === []) {
            return;
        }

        $message = $this->buildMessage($title, $body, $data);

        try {
            $report = $this->messaging->sendMulticast($message, $tokens);
        } catch (Throwable $e) {
            report($e);

            return;
        }

        // Invalid (malformed) and unknown (app uninstalled) tokens are both dead ends.
        $dead = [...$report->invalidTokens(), ...$report->unknownTokens()];

        if ($dead !== []) {
            DeviceToken::query()->whereIn('token', $dead)->delete();
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
    public function buildMessage(string $title, string $body, array $data = []): CloudMessage
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
            'tag' => $isChat && isset($data['conversation_id']) ? 'conversation-'.$data['conversation_id'] : null,
            // An app build without this drawable falls back to the manifest's default icon.
            'icon' => ($icon = NotificationIcon::key($type, $data['status'] ?? null)) !== null ? 'ic_stat_'.$icon : null,
        ]);

        return CloudMessage::new()
            ->withNotification(['title' => $title, 'body' => $body])
            ->withData($data)
            ->withAndroidConfig(['priority' => 'high', 'notification' => $androidNotification]);
    }
}
