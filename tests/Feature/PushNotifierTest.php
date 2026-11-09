<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\SellerOrderStatusUpdated;
use App\Services\PushNotifier;
use App\Support\NotificationIcon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PushNotifierTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The app runs with QUEUE_CONNECTION=sync, so a chat message's own HTTP
     * request constructs this class. A missing Firebase credential — the
     * state of a fresh deploy before the secret is set in Render — must
     * never break sending a message.
     */
    public function test_sending_never_throws_when_firebase_credentials_are_unconfigured(): void
    {
        Config::set('services.fcm.credentials', null);

        $user = User::factory()->create();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'tok-1', 'platform' => 'android']);

        (new PushNotifier)->sendToUser($user, 'Title', 'Body');

        $this->assertDatabaseHas('device_tokens', ['token' => 'tok-1']);
    }

    public function test_sending_never_throws_when_firebase_credentials_are_malformed(): void
    {
        Config::set('services.fcm.credentials', 'not valid json and not a real path');

        $user = User::factory()->create();

        (new PushNotifier)->sendToUser($user, 'Title', 'Body');

        $this->assertTrue(true);
    }

    public function test_chat_pushes_use_the_messages_channel_and_stack_per_conversation(): void
    {
        $message = json_decode(json_encode((new PushNotifier)->buildMessage('Ana', 'Hi', ['type' => 'chat', 'conversation_id' => 'c-42'])), true);

        $this->assertSame('chat_messages', $message['android']['notification']['channel_id']);
        $this->assertSame('conversation-c-42', $message['android']['notification']['tag']);
    }

    public function test_order_pushes_use_the_orders_channel(): void
    {
        $message = json_decode(json_encode((new PushNotifier)->buildMessage('Order #1', 'Shipped', ['type' => 'order', 'order_id' => 'o-7'])), true);

        $this->assertSame('order_updates', $message['android']['notification']['channel_id']);
        $this->assertArrayNotHasKey('tag', $message['android']['notification']);
    }

    public function test_each_push_type_carries_its_own_status_bar_icon(): void
    {
        $icon = fn (array $data): ?string => json_decode(json_encode((new PushNotifier)->buildMessage('Hi', 'Hello', $data)), true)['android']['notification']['icon'] ?? null;

        $this->assertSame('ic_stat_message', $icon(['type' => 'chat', 'conversation_id' => 'c-1']));
        $this->assertSame('ic_stat_shipped', $icon(['type' => 'order', 'order_id' => 'o-1', 'status' => 'shipped']));
        $this->assertSame('ic_stat_cancelled', $icon(['type' => 'order', 'order_id' => 'o-1', 'status' => 'cancelled']));
        $this->assertSame('ic_stat_announcement', $icon(['type' => 'announcement']));
        $this->assertNull($icon(['type' => 'something_new']));
    }

    public function test_every_icon_key_ships_a_web_icon_badge_and_android_drawable(): void
    {
        $keys = collect([['chat'], ['seller_order'], ['order'], ['order', 'confirmed'], ['order', 'processing'], ['order', 'shipped'], ['order', 'delivered'], ['order', 'cancelled'], ['order_issue'], ['return_case'], ['announcement'], ['cart']])
            ->map(fn (array $args): ?string => NotificationIcon::key(...$args));

        $this->assertNotContains(null, $keys);
        foreach ($keys->unique() as $key) {
            $this->assertFileExists(public_path("images/notifications/{$key}.png"));
            $this->assertFileExists(public_path("images/notifications/{$key}-badge.png"));
            $this->assertFileExists(base_path("mobile/android/app/src/main/res/drawable/ic_stat_{$key}.xml"));
        }
    }

    public function test_web_pushes_use_the_icon_for_their_type_and_status(): void
    {
        $user = User::factory()->create();
        $notification = new SellerOrderStatusUpdated('order-1', 'delivered', 'Order #JM-1', 'Delivered.');
        $notification->id = 'n-1';

        $message = $notification->toWebPush($user, $notification)->toArray();

        $this->assertSame(asset('images/notifications/delivered.png'), $message['icon']);
        $this->assertSame(asset('images/notifications/delivered-badge.png'), $message['badge']);
        $this->assertSame(asset('images/notification-icon.png'), NotificationIcon::url('something_new'));
    }

    public function test_announcements_and_cart_reminders_use_the_updates_channel(): void
    {
        foreach (['announcement', 'cart'] as $type) {
            $message = json_decode(json_encode((new PushNotifier)->buildMessage('Hi', 'Hello', ['type' => $type])), true);

            $this->assertSame('marketplace_updates', $message['android']['notification']['channel_id']);
        }
    }
}
