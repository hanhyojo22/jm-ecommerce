<?php

namespace Tests\Feature;

use App\Livewire\NotificationBell;
use App\Models\User;
use App\Notifications\ChatMessageReceived;
use App\Notifications\SellerOrderStatusUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_order_status_updated_notification_has_the_expected_payload(): void
    {
        $notification = new SellerOrderStatusUpdated('order-1', 'confirmed', 'Order #JM-1', 'Store confirmed your order.');
        $user = User::factory()->create();

        $this->assertSame(['database', 'broadcast'], $notification->via($user));
        $this->assertSame([
            'type' => 'order',
            'order_id' => 'order-1',
            'status' => 'confirmed',
            'title' => 'Order #JM-1',
            'body' => 'Store confirmed your order.',
        ], $notification->toArray($user));
    }

    public function test_bell_counts_order_notifications_without_showing_chat_messages(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $user->notify(new ChatMessageReceived('Jane', 'Is this still available?', 'conversation-1'));
        $user->notify(new ChatMessageReceived('Jane', 'Still there?', 'conversation-1'));
        $user->notify(new SellerOrderStatusUpdated('order-1', 'confirmed', 'Order #JM-1', 'Store confirmed your order.'));

        Livewire::test(NotificationBell::class)
            ->assertViewHas('unreadCount', 1)
            ->assertSee('Store confirmed your order.')
            ->assertDontSee('Is this still available?')
            ->assertDontSee('Still there?');
    }

    public function test_mark_all_read_clears_the_badge(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $user->notify(new ChatMessageReceived('Jane', 'Is this still available?', 'conversation-1'));
        $user->notify(new SellerOrderStatusUpdated('order-1', 'confirmed', 'Order #JM-1', 'Store confirmed your order.'));

        Livewire::test(NotificationBell::class)
            ->call('markAllRead')
            ->assertViewHas('unreadCount', 0)
            ->assertDontSee('Store confirmed your order.');

        $this->assertSame(1, $user->unreadNotifications()->count());
        $this->assertSame(ChatMessageReceived::class, $user->unreadNotifications()->first()->type);
    }

    public function test_opening_the_bell_reads_notifications_and_clears_them_on_close(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $user->notify(new SellerOrderStatusUpdated('order-1', 'confirmed', 'Order #JM-1', 'Store confirmed your order.'));
        $otherUser->notify(new SellerOrderStatusUpdated('order-2', 'confirmed', 'Other order', 'Another customer notification.'));

        Livewire::actingAs($user)->test(NotificationBell::class)
            ->call('openNotifications')
            ->assertViewHas('unreadCount', 0)
            ->assertSee('Store confirmed your order.')
            ->call('closeNotifications')
            ->call('openNotifications')
            ->assertDontSee('Store confirmed your order.');

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $otherUser->unreadNotifications()->count());
        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_clicking_a_notification_clears_it_and_keeps_its_destination(): void
    {
        $user = User::factory()->create();
        $user->notify(new SellerOrderStatusUpdated('order-1', 'confirmed', 'Order #JM-1', 'Store confirmed your order.'));
        $notification = $user->notifications()->first();

        Livewire::actingAs($user)->test(NotificationBell::class)
            ->call('openNotifications')
            ->call('goTo', $notification->id)
            ->assertRedirect(route('orders.show', 'order-1'))
            ->assertSet('openedNotificationIds', []);

        Livewire::test(NotificationBell::class)->assertDontSee('Store confirmed your order.');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_chat_notification_cannot_be_opened_from_the_bell(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $user->notify(new ChatMessageReceived('Jane', 'Is this still available?', 'conversation-1'));
        $chatNotification = $user->notifications()->first();

        Livewire::test(NotificationBell::class)->call('goTo', $chatNotification->id)->assertNoRedirect();

        $this->assertNull($chatNotification->fresh()->read_at);
    }

    public function test_a_notification_does_not_leak_to_another_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        $owner->notify(new SellerOrderStatusUpdated('order-1', 'confirmed', 'Order #JM-1', 'Store confirmed your order.'));
        $ownersNotificationId = $owner->notifications()->first()->id;

        Livewire::test(NotificationBell::class)
            ->assertDontSee('Store confirmed your order.')
            ->call('goTo', $ownersNotificationId);

        $this->assertDatabaseHas('notifications', ['id' => $ownersNotificationId, 'read_at' => null]);
    }

    public function test_a_configured_vapid_keypair_adds_the_webpush_channel(): void
    {
        config(['webpush.vapid.public_key' => 'test-public', 'webpush.vapid.private_key' => 'test-private']);

        $notification = new ChatMessageReceived('Jane', 'Is this still available?', 'conversation-1');

        $this->assertSame(['database', 'broadcast', WebPushChannel::class], $notification->via(User::factory()->make()));
    }

    public function test_a_push_subscription_from_an_untrusted_host_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(NotificationBell::class)
            ->call('savePushSubscription', [
                'endpoint' => 'https://attacker.example.com/collect',
                'keys' => ['p256dh' => str_repeat('a', 32), 'auth' => str_repeat('b', 16)],
            ])
            ->assertReturned(false);

        $this->assertDatabaseMissing('push_subscriptions', ['subscribable_id' => $user->id]);
    }

    public function test_a_push_subscription_from_a_trusted_host_is_saved(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(NotificationBell::class)
            ->call('savePushSubscription', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
                'keys' => ['p256dh' => str_repeat('a', 32), 'auth' => str_repeat('b', 16)],
            ])
            ->assertReturned(true);

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
        ]);
    }

    public function test_removing_a_push_subscription_deletes_the_row(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/abc123', str_repeat('a', 32), str_repeat('b', 16));

        Livewire::test(NotificationBell::class)->call('removePushSubscription', 'https://fcm.googleapis.com/fcm/send/abc123');

        $this->assertDatabaseMissing('push_subscriptions', ['subscribable_id' => $user->id]);
    }

    public function test_removing_a_push_subscription_cannot_delete_another_users_row(): void
    {
        $owner = User::factory()->create();
        $owner->updatePushSubscription('https://fcm.googleapis.com/fcm/send/abc123', str_repeat('a', 32), str_repeat('b', 16));

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        Livewire::test(NotificationBell::class)->call('removePushSubscription', 'https://fcm.googleapis.com/fcm/send/abc123');

        $this->assertDatabaseHas('push_subscriptions', ['subscribable_id' => $owner->id]);
    }
}
