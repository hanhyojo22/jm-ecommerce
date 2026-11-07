<?php

namespace Tests\Feature;

use App\Livewire\NotificationBell;
use App\Models\User;
use App\Notifications\ChatMessageReceived;
use App\Notifications\SellerOrderStatusUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_unread_count_reflects_unread_notifications(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $user->notify(new ChatMessageReceived('Jane', 'Is this still available?', 'conversation-1'));
        $user->notify(new ChatMessageReceived('Jane', 'Still there?', 'conversation-1'));
        $read = $user->notifications()->latest()->first();
        $read->markAsRead();

        Livewire::test(NotificationBell::class)
            ->assertViewHas('unreadCount', 1)
            ->assertSee('Is this still available?');
    }

    public function test_mark_all_read_clears_the_badge(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $user->notify(new ChatMessageReceived('Jane', 'Is this still available?', 'conversation-1'));
        $user->notify(new ChatMessageReceived('Jane', 'Still there?', 'conversation-1'));

        Livewire::test(NotificationBell::class)->call('markAllRead');

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_a_notification_does_not_leak_to_another_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        $owner->notify(new ChatMessageReceived('Jane', 'Is this still available?', 'conversation-1'));
        $ownersNotificationId = $owner->notifications()->first()->id;

        Livewire::test(NotificationBell::class)
            ->assertDontSee('Is this still available?')
            ->call('goTo', $ownersNotificationId);

        $this->assertDatabaseHas('notifications', ['id' => $ownersNotificationId, 'read_at' => null]);
    }
}
