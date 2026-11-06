<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Listeners\SendChatMessagePush;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class SendChatMessagePushTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_buyer_message_pushes_every_active_seller_staff_member(): void
    {
        [$buyer, $owner, $manager, $conversation] = $this->conversationWithTwoStaff();

        $message = $conversation->messages()->create([
            'sender_id' => $buyer->id,
            'client_message_id' => (string) Str::uuid(),
            'type' => 'text',
            'body' => 'Is this still available?',
        ]);

        $notifier = Mockery::mock(PushNotifier::class);
        $notifier->shouldReceive('sendToUser')
            ->once()
            ->with(
                Mockery::on(fn (User $user) => $user->is($owner)),
                $buyer->name,
                'Is this still available?',
                ['type' => 'chat', 'conversation_id' => $conversation->id],
            );
        $notifier->shouldReceive('sendToUser')
            ->once()
            ->with(
                Mockery::on(fn (User $user) => $user->is($manager)),
                $buyer->name,
                'Is this still available?',
                ['type' => 'chat', 'conversation_id' => $conversation->id],
            );

        (new SendChatMessagePush($notifier))->handle(new MessageSent($message));
    }

    public function test_a_seller_message_pushes_only_the_buyer(): void
    {
        [$buyer, $owner, , $conversation] = $this->conversationWithTwoStaff();

        $message = $conversation->messages()->create([
            'sender_id' => $owner->id,
            'client_message_id' => (string) Str::uuid(),
            'type' => 'text',
            'body' => 'Yes, 5 left.',
        ]);

        $notifier = Mockery::mock(PushNotifier::class);
        $notifier->shouldReceive('sendToUser')
            ->once()
            ->with(
                Mockery::on(fn (User $user) => $user->is($buyer)),
                $owner->name,
                'Yes, 5 left.',
                ['type' => 'chat', 'conversation_id' => $conversation->id],
            );

        (new SendChatMessagePush($notifier))->handle(new MessageSent($message));
    }

    public function test_a_deactivated_seller_staff_member_is_not_pushed(): void
    {
        [$buyer, $owner, $manager, $conversation] = $this->conversationWithTwoStaff();
        $manager->update(['is_active' => false]);

        $message = $conversation->messages()->create([
            'sender_id' => $buyer->id,
            'client_message_id' => (string) Str::uuid(),
            'type' => 'text',
            'body' => 'Still there?',
        ]);

        $notifier = Mockery::mock(PushNotifier::class);
        $notifier->shouldReceive('sendToUser')
            ->once()
            ->with(Mockery::on(fn (User $user) => $user->is($owner)), $buyer->name, 'Still there?', [
                'type' => 'chat',
                'conversation_id' => $conversation->id,
            ]);

        (new SendChatMessagePush($notifier))->handle(new MessageSent($message));
    }

    public function test_staff_of_a_suspended_store_are_not_pushed(): void
    {
        [$buyer, , , $conversation] = $this->conversationWithTwoStaff();
        $conversation->store->update(['status' => 'suspended']);

        $message = $conversation->messages()->create([
            'sender_id' => $buyer->id,
            'client_message_id' => (string) Str::uuid(),
            'type' => 'text',
            'body' => 'Hello?',
        ]);

        $notifier = Mockery::mock(PushNotifier::class);
        $notifier->shouldNotReceive('sendToUser');

        (new SendChatMessagePush($notifier))->handle(new MessageSent($message));
    }

    public function test_a_deactivated_buyer_is_not_pushed(): void
    {
        [$buyer, $owner, , $conversation] = $this->conversationWithTwoStaff();
        $buyer->update(['is_active' => false]);

        $message = $conversation->messages()->create([
            'sender_id' => $owner->id,
            'client_message_id' => (string) Str::uuid(),
            'type' => 'text',
            'body' => 'Still there?',
        ]);

        $notifier = Mockery::mock(PushNotifier::class);
        $notifier->shouldNotReceive('sendToUser');

        (new SendChatMessagePush($notifier))->handle(new MessageSent($message));
    }

    /** @return array{0: User, 1: User, 2: User, 3: Conversation} buyer, seller owner, seller manager, conversation */
    private function conversationWithTwoStaff(): array
    {
        $buyer = User::factory()->create();
        $owner = User::factory()->seller()->create();
        $manager = User::factory()->seller()->create();
        $suffix = str()->lower(str()->random(8));

        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => 'active']);
        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => "Store {$suffix}",
            'slug' => "store-{$suffix}",
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => 'active',
        ]);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner']);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $manager->id, 'role' => 'manager']);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Chat Tote',
            'slug' => "chat-tote-{$suffix}",
            'sku' => "CHAT-TOTE-{$suffix}",
            'price' => '199.00',
            'stock_quantity' => 5,
            'status' => 'active',
        ]);

        $conversation = Conversation::create([
            'buyer_id' => $buyer->id,
            'store_id' => $store->id,
            'product_id' => $product->id,
        ]);

        return [$buyer, $owner, $manager, $conversation];
    }
}
