<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\MarketplaceNotice;
use App\Services\PushNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendAdminAnnouncement implements ShouldQueue
{
    use Queueable;

    /**
     * Nothing records who already got the announcement, so a retry would
     * push everyone reached before the failure a second time. Each job sends
     * one small batch so it finishes well inside the database queue's 90s
     * retry_after; a longer job would be reserved again and marked failed.
     */
    public int $tries = 1;

    public int $timeout = 60;

    /**
     * @param  list<string>|null  $batchUserIds
     */
    public function __construct(
        public string $announcementId,
        public ?array $batchUserIds = null,
    ) {}

    /**
     * A storefront link becomes an explicit app destination so the mobile app
     * opens the store screen even when the web host differs from the API host.
     */
    public static function storeSlug(string $url): ?string
    {
        if (preg_match('#^/stores/([^/]+)/?$#', parse_url($url, PHP_URL_PATH) ?: '', $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @return Builder<User>
     */
    public static function recipients(string $audience, ?string $userId = null): Builder
    {
        $query = User::query()->where('is_active', true);

        return match ($audience) {
            'customers' => $query->where('account_type', 'buyer'),
            // Same membership rule as SendChatMessagePush: active staff of an active store.
            'stores' => $query->where('account_type', 'seller')
                ->whereHas('tenantMemberships', fn ($membership) => $membership
                    ->whereIn('role', ['owner', 'manager', 'staff'])
                    ->whereHas('tenant', fn ($tenant) => $tenant
                        ->where('status', 'active')
                        ->whereHas('store', fn ($store) => $store->where('status', 'active')))),
            'customer' => $query->whereKey($userId),
        };
    }

    public function handle(PushNotifier $notifier): void
    {
        $announcement = Announcement::query()->find($this->announcementId);

        if ($announcement === null || $announcement->cancelled_at !== null) {
            return;
        }

        $recipients = self::recipients($announcement->audience, $announcement->user_id);

        if ($this->batchUserIds === null) {
            $recipients->select('id')->chunkById(25, function ($users) use ($announcement): void {
                self::dispatch($announcement->id, $users->modelKeys());
            });

            return;
        }

        // Re-checked so an account deactivated after queueing is skipped.
        $storeSlug = self::storeSlug($announcement->url);

        $recipients->whereKey($this->batchUserIds)->get()->each(function (User $user) use ($notifier, $announcement, $storeSlug): void {
            $notifier->sendToUser($user, $announcement->title, $announcement->body, array_filter([
                'type' => 'announcement',
                'url' => $announcement->url,
                'image_url' => $announcement->image_url,
                'store_slug' => $storeSlug,
            ]));

            try {
                $user->notify(new MarketplaceNotice('announcement', $announcement->title, $announcement->body, $announcement->url, $announcement->image_url));
            } catch (Throwable $exception) {
                report($exception);
            }
        });

        $announcement->increment('sent_count', count($this->batchUserIds));
    }
}
