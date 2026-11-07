<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ChatMessageReceived;
use App\Notifications\NewSellerOrderPlaced;
use App\Notifications\SellerOrderStatusUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class PushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_save_a_push_subscription(): void
    {
        $this->postJson('/push-subscriptions', $this->subscriptionPayload())
            ->assertUnauthorized();

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_a_trusted_push_subscription_is_saved_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/push-subscriptions', $this->subscriptionPayload())
            ->assertNoContent();

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
        ]);
    }

    public function test_a_push_subscription_from_an_untrusted_host_is_rejected_with_validation_errors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/push-subscriptions', $this->subscriptionPayload('https://attacker.example.com/collect'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('endpoint');

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_a_push_subscription_that_is_not_https_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/push-subscriptions', $this->subscriptionPayload('http://fcm.googleapis.com/fcm/send/abc123'))
            ->assertUnprocessable();

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_a_push_subscription_requires_valid_keys(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/push-subscriptions', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
                'keys' => ['p256dh' => '', 'auth' => ''],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('endpoint');
    }

    public function test_a_saved_push_subscription_can_be_removed(): void
    {
        $user = User::factory()->create();
        $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/abc123', str_repeat('a', 32), str_repeat('b', 16));

        $this->actingAs($user)
            ->deleteJson('/push-subscriptions', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123'])
            ->assertNoContent();

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_removing_another_users_push_subscription_does_nothing(): void
    {
        $owner = User::factory()->create();
        $owner->updatePushSubscription('https://fcm.googleapis.com/fcm/send/abc123', str_repeat('a', 32), str_repeat('b', 16));

        $this->actingAs(User::factory()->create())
            ->deleteJson('/push-subscriptions', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123'])
            ->assertNoContent();

        $this->assertDatabaseHas('push_subscriptions', ['subscribable_id' => $owner->id]);
    }

    public function test_the_vapid_public_key_is_exposed_only_when_configured(): void
    {
        config(['webpush.vapid.public_key' => null]);

        $this->getJson('/push/vapid-public-key')->assertNotFound();

        config(['webpush.vapid.public_key' => 'test-public-key']);

        $this->getJson('/push/vapid-public-key')
            ->assertOk()
            ->assertJson(['key' => 'test-public-key']);
    }

    public function test_a_signed_read_url_marks_the_notification_read_without_a_session(): void
    {
        $user = User::factory()->create();
        $user->notify(new ChatMessageReceived('Jane', 'Is this still available?', 'conversation-1'));
        $notification = $user->notifications()->first();

        $url = URL::temporarySignedRoute(
            'push-notifications.read',
            now()->addMinutes(5),
            ['notification' => $notification->id],
        );

        $this->get($url)->assertNoContent();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_an_unsigned_read_url_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->notify(new ChatMessageReceived('Jane', 'Is this still available?', 'conversation-1'));
        $notification = $user->notifications()->first();

        $this->get(route('push-notifications.read', $notification->id))
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_a_signed_read_url_cannot_be_reused_for_another_notification(): void
    {
        $user = User::factory()->create();
        $user->notify(new ChatMessageReceived('Jane', 'First message', 'conversation-1'));
        $user->notify(new ChatMessageReceived('Jane', 'Second message', 'conversation-1'));
        [$first, $second] = $user->notifications()->latest()->get()->all();

        $url = URL::temporarySignedRoute(
            'push-notifications.read',
            now()->addMinutes(5),
            ['notification' => $first->id],
        );

        $this->get(str_replace($first->id, $second->id, $url))->assertForbidden();

        $this->assertNull($second->fresh()->read_at);
    }

    public function test_every_push_notification_carries_click_and_read_urls(): void
    {
        $user = User::factory()->create();
        $notificationId = (string) Str::uuid();

        $notifications = [
            [new ChatMessageReceived('Jane', 'Is this still available?', 'conversation-1'), route('messages.show', 'conversation-1'), 'chat-conversation-1'],
            [new SellerOrderStatusUpdated('order-1', 'confirmed', 'Order #JM-1', 'Store confirmed your order.'), route('orders.show', 'order-1'), 'order-order-1'],
            [new NewSellerOrderPlaced('seller-order-1', 'New order', 'A buyer placed an order.'), route('seller.orders.show', 'seller-order-1'), 'seller-order-seller-order-1'],
        ];

        foreach ($notifications as [$notification, $expectedUrl, $expectedTag]) {
            $notification->id = $notificationId;
            $payload = $notification->toWebPush($user, $notification)->toArray();

            $this->assertSame($expectedTag, $payload['tag']);
            $this->assertTrue($payload['renotify']);
            $this->assertSame(asset('images/notification-icon.png'), $payload['icon']);
            $this->assertSame(asset('images/notification-badge.png'), $payload['badge']);
            $this->assertSame($expectedUrl, $payload['data']['url']);
            $this->assertSame($notificationId, $payload['data']['notification_id']);

            $readRequest = Request::create($payload['data']['read_url']);

            $this->assertTrue($readRequest->hasValidSignature());
            $this->assertStringContainsString('/push-notifications/'.$notificationId.'/read', $payload['data']['read_url']);
        }
    }

    /**
     * @return array{endpoint: string, keys: array{p256dh: string, auth: string}}
     */
    private function subscriptionPayload(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123'): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => str_repeat('a', 32),
                'auth' => str_repeat('b', 16),
            ],
        ];
    }
}
