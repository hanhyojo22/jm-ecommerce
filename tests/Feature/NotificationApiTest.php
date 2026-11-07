<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ChatMessageReceived;
use App\Notifications\SellerOrderStatusUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_users_non_chat_notifications_are_listed_and_counted(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create();
        $user->notify(new ChatMessageReceived('Seller', 'Hello', 'conversation-1'));
        $user->notify(new SellerOrderStatusUpdated('order-1', 'confirmed', 'Your order', 'Order confirmed'));
        $other->notify(new SellerOrderStatusUpdated('order-2', 'confirmed', 'Other order', 'Private'));

        $this->actingAs($user)->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Your order')
            ->assertDontSee('Hello')
            ->assertDontSee('Private');
    }

    public function test_read_actions_leave_chat_unread_and_cannot_change_another_users_notification(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create();
        $user->notify(new ChatMessageReceived('Seller', 'Hello', 'conversation-1'));
        $user->notify(new SellerOrderStatusUpdated('order-1', 'confirmed', 'Your order', 'Order confirmed'));
        $user->notify(new SellerOrderStatusUpdated('order-3', 'shipped', 'Another order', 'On the way'));
        $other->notify(new SellerOrderStatusUpdated('order-2', 'confirmed', 'Other order', 'Private'));
        $chat = $user->notifications()->where('type', ChatMessageReceived::class)->firstOrFail();
        $orders = $user->notifications()->where('type', SellerOrderStatusUpdated::class)->orderBy('id')->get();
        $othersOrder = $other->notifications()->firstOrFail();

        $this->actingAs($user)->postJson("/api/v1/notifications/{$othersOrder->id}/read")->assertNotFound();
        $this->postJson("/api/v1/notifications/{$chat->id}/read")->assertNotFound();
        $this->postJson("/api/v1/notifications/{$orders[0]->id}/read")->assertNoContent();
        $this->assertNotNull($orders[0]->fresh()->read_at);
        $this->assertNull($orders[1]->fresh()->read_at);
        $this->postJson('/api/v1/notifications/read')->assertNoContent();

        $this->assertNotNull($orders[1]->fresh()->read_at);
        $this->assertNull($chat->fresh()->read_at);
        $this->assertNull($othersOrder->fresh()->read_at);
    }

    public function test_notifications_require_sign_in(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }
}
