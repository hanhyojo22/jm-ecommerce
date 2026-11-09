<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\PushNotifier;
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

    public function test_announcements_and_cart_reminders_use_the_updates_channel(): void
    {
        foreach (['announcement', 'cart'] as $type) {
            $message = json_decode(json_encode((new PushNotifier)->buildMessage('Hi', 'Hello', ['type' => $type])), true);

            $this->assertSame('marketplace_updates', $message['android']['notification']['channel_id']);
        }
    }
}
