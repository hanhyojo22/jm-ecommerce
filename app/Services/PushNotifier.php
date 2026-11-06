<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
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
        // The app runs with QUEUE_CONNECTION=sync, so this constructor runs
        // inline on the request that sends a chat message. A missing or
        // malformed Firebase credential must never break that request.
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

        $message = CloudMessage::new()
            ->withNotification(['title' => $title, 'body' => $body])
            ->withData($data);

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
}
