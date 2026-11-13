<?php

namespace Tests\Feature;

use App\Actions\SendConversationMessage;
use App\Events\ConversationInboxChanged;
use App\Events\ConversationReceiptUpdated;
use App\Events\MessageSent;
use App\Events\TypingStatusChanged;
use App\Livewire\Chat\Inbox;
use App\Livewire\Chat\Thread;
use App\Models\BlockedUser;
use App\Models\Conversation;
use App\Models\ConversationReadState;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_events_broadcast_without_waiting_for_the_queue_worker(): void
    {
        foreach ([MessageSent::class, ConversationReceiptUpdated::class, ConversationInboxChanged::class, TypingStatusChanged::class] as $event) {
            $implements = class_implements($event);
            $this->assertContains(ShouldBroadcastNow::class, $implements, $event.' would sit in the queue before reaching the other person.');
            $this->assertContains(ShouldRescue::class, $implements, $event.' would fail the triggering request whenever Reverb is slow or unreachable.');
        }
    }

    public function test_new_conversation_and_message_notify_only_authorized_inbox_recipients(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $seller = User::factory()->seller()->create();
        $otherSeller = User::factory()->seller()->create();
        $product = $this->product();
        TenantMember::create(['tenant_id' => $product->tenant_id, 'user_id' => $seller->id, 'role' => 'staff']);
        Event::fake([ConversationInboxChanged::class, MessageSent::class]);

        $this->actingAs($buyer)->post(route('messages.products.start', $product))->assertRedirect();
        $conversation = Conversation::query()->firstOrFail();
        Event::assertDispatchedTimes(ConversationInboxChanged::class, 1);

        $channels = (new ConversationInboxChanged($conversation))->broadcastOn();
        $channelNames = array_map(fn ($channel): string => $channel->name, $channels);
        $this->assertContains('private-chat.inbox.'.$buyer->id, $channelNames);
        $this->assertContains('private-chat.inbox.'.$seller->id, $channelNames);
        $this->assertNotContains('private-chat.inbox.'.$otherSeller->id, $channelNames);
        $this->assertSame(['private-chat.inbox.'.$buyer->id], array_map(fn ($channel) => $channel->name, (new ConversationInboxChanged($conversation, $buyer->id))->broadcastOn()));

        app(SendConversationMessage::class)->handle($conversation, $buyer, 'Hello', (string) str()->uuid());
        Event::assertDispatchedTimes(ConversationInboxChanged::class, 2);

        $this->post(route('messages.products.start', $product))->assertRedirect();
        Event::assertDispatchedTimes(ConversationInboxChanged::class, 2);
    }

    public function test_inbox_broadcast_channel_rejects_another_user_and_inactive_accounts(): void
    {
        config(['broadcasting.default' => 'reverb']);
        Broadcast::forgetDrivers();
        require base_path('routes/channels.php');
        $buyer = User::factory()->create();
        $otherBuyer = User::factory()->create();
        $payload = ['socket_id' => '123.456', 'channel_name' => 'private-chat.inbox.'.$buyer->id];

        $this->actingAs($buyer)->postJson('/broadcasting/auth', $payload)->assertOk();
        $this->actingAs($otherBuyer)->postJson('/broadcasting/auth', $payload)->assertForbidden();
        $buyer->forceFill(['is_active' => false])->save();
        $this->actingAs($buyer)->postJson('/broadcasting/auth', $payload)->assertForbidden();
    }

    public function test_verified_buyer_can_start_only_one_product_conversation(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $product = $this->product();

        $this->actingAs($buyer)->post(route('messages.products.start', $product))->assertRedirect();
        $this->actingAs($buyer)->post(route('messages.products.start', $product))->assertRedirect();

        $this->assertSame(1, Conversation::count());
        $this->assertDatabaseHas('conversations', ['buyer_id' => $buyer->id, 'store_id' => $product->store_id]);
    }

    public function test_opening_a_product_chat_shows_pending_context_without_saving_a_message(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $product = $this->product();

        $response = $this->actingAs($buyer)->post(route('messages.products.start', $product))->assertRedirect();

        $this->get($response->headers->get('Location'))->assertOk()->assertSee($product->name);
        $this->assertDatabaseCount('conversation_messages', 0);
    }

    public function test_buyer_starts_one_conversation_from_their_own_seller_order(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $otherBuyer = User::factory()->create(['email_verified_at' => now()]);
        $product = $this->product();
        $order = Order::create([
            'user_id' => $buyer->id,
            'number' => 'CHAT-ORDER-001',
            'status' => 'pending',
            'recipient_name' => 'Buyer',
            'phone' => '+639171234567',
            'address_line' => '1 Market Street',
            'city' => 'Manila',
            'subtotal' => '199.00',
            'shipping_amount' => '0.00',
            'total' => '199.00',
        ]);
        $sellerOrder = $order->sellerOrders()->create([
            'tenant_id' => $product->tenant_id,
            'store_id' => $product->store_id,
            'status' => 'pending',
            'subtotal' => '199.00',
            'shipping_amount' => '0.00',
            'total' => '199.00',
        ]);

        $this->actingAs($buyer)->post(route('messages.seller-orders.start', $sellerOrder))->assertRedirect();
        $this->actingAs($buyer)->post(route('messages.seller-orders.start', $sellerOrder))->assertRedirect();

        $this->assertSame(1, Conversation::count());
        $this->assertDatabaseHas('conversations', [
            'buyer_id' => $buyer->id,
            'store_id' => $product->store_id,
        ]);

        $this->actingAs($otherBuyer)->post(route('messages.seller-orders.start', $sellerOrder))->assertNotFound();
    }

    public function test_product_conversation_start_is_rate_limited(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $product = $this->product();

        for ($index = 0; $index < 10; $index++) {
            $this->actingAs($buyer)
                ->post(route('messages.products.start', $product))
                ->assertRedirect();
        }

        $this->actingAs($buyer)
            ->post(route('messages.products.start', $product))
            ->assertTooManyRequests();

        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_buyer_cannot_view_another_buyers_conversation(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $otherBuyer = User::factory()->create(['email_verified_at' => now()]);
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);

        $this->actingAs($otherBuyer)->get(route('messages.show', $conversation))->assertNotFound();
    }

    public function test_admin_cannot_access_chat_inbox(): void
    {
        $admin = User::factory()->administrator()->create(['email_verified_at' => now()]);

        $this->actingAs($admin)->get(route('messages.index'))->assertForbidden();
    }

    public function test_verified_buyer_can_open_the_messages_inbox_page(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($buyer)
            ->get(route('messages.index'))
            ->assertSee('Contact stores about products and orders.');
    }

    public function test_seller_can_open_the_messages_inbox_page(): void
    {
        $product = $this->product();
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $product->tenant_id, 'user_id' => $seller->id, 'role' => 'staff']);

        $this->actingAs($seller)
            ->get(route('messages.index'))
            ->assertSee('Your customer conversations.');
    }

    public function test_seller_buying_and_selling_conversations_stay_in_their_respective_inboxes(): void
    {
        $ownProduct = $this->product();
        $otherProduct = $this->product();
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $ownProduct->tenant_id, 'user_id' => $seller->id, 'role' => 'owner']);
        $customer = User::factory()->create();
        $sale = Conversation::create(['buyer_id' => $customer->id, 'store_id' => $ownProduct->store_id, 'product_id' => $ownProduct->id]);
        $sale->messages()->create(['sender_id' => $customer->id, 'type' => 'text', 'body' => 'Still in stock?']);

        $this->actingAs($seller)->post(route('messages.products.start', $otherProduct))->assertRedirect();
        $purchase = Conversation::query()->where('buyer_id', $seller->id)->sole();
        $purchase->messages()->create(['sender_id' => $seller->id, 'type' => 'text', 'body' => 'Is this still available?']);

        $this->actingAs($seller)->get(route('messages.index', ['context' => 'buying']))
            ->assertOk()->assertSee(route('messages.show', $purchase), false)
            ->assertDontSee(route('messages.show', $sale), false);
        $this->actingAs($seller)->get(route('messages.index', ['context' => 'selling']))
            ->assertOk()->assertSee(route('messages.show', $sale), false)
            ->assertDontSee(route('messages.show', $purchase), false);
        $this->actingAs($seller)->get(route('messages.show', $purchase))
            ->assertOk()->assertSee($otherProduct->store->name)->assertDontSee('Your customer conversations.');
    }

    public function test_starting_a_conversation_without_sending_a_message_does_not_appear_in_either_inbox(): void
    {
        $product = $this->product();
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $product->tenant_id, 'user_id' => $seller->id, 'role' => 'owner']);

        $this->actingAs($buyer)->post(route('messages.products.start', $product))->assertRedirect();
        $conversation = Conversation::query()->where('buyer_id', $buyer->id)->sole();

        $this->actingAs($buyer)->get(route('messages.index'))
            ->assertOk()->assertDontSee(route('messages.show', $conversation), false);
        $this->actingAs($seller)->get(route('messages.index'))
            ->assertOk()->assertDontSee(route('messages.show', $conversation), false);
    }

    public function test_seller_cannot_start_a_customer_conversation_with_their_own_store(): void
    {
        $product = $this->product();
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $product->tenant_id, 'user_id' => $seller->id, 'role' => 'manager']);

        $this->actingAs($seller)->post(route('messages.products.start', $product))->assertForbidden();

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_buyer_can_open_a_conversation_page(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);

        $this->actingAs($buyer)
            ->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertSee('data-conversation-thread')
            ->assertSee('x-on:input.debounce.500ms="$wire.startTyping()"', false);
    }

    public function test_order_inquiry_card_shows_the_first_product_with_its_rating(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id]);
        $items = collect(['Rice cooker', 'Spare lid', 'Measuring cup', 'Steamer tray'])
            ->map(fn (string $name): array => ['name' => $name, 'variant' => 'White', 'quantity' => 2, 'image_path' => null, 'rating' => 4.5, 'rating_count' => 12])->all();
        $conversation->messages()->create(['sender_id' => $buyer->id, 'body' => 'When will this ship?', 'context_snapshot' => [
            'type' => 'order', 'number' => 'JM-1042', 'total' => '199.00', 'status' => 'shipped',
            'order_id' => 'missing-order', 'item_count' => 4, 'items' => $items,
        ]]);

        $this->actingAs($buyer)->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertSeeInOrder(['+3', 'Rice cooker', 'Shipped', '4.5 (12)', 'White · ×2', '₱199.00'])
            ->assertDontSee('Steamer tray');
    }

    public function test_seller_can_open_a_conversation_page(): void
    {
        $product = $this->product();
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $product->tenant_id, 'user_id' => $seller->id, 'role' => 'staff']);
        $buyer = User::factory()->create();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);

        $this->actingAs($seller)
            ->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertSee('data-conversation-thread')
            ->assertSee('Write a message')
            // Still Alpine, so a quick reply costs no server round trip.
            ->assertSee('x-on:click="useQuickReply(\'Your order is being prepared.\')"', false)
            ->assertDontSee('wire:click="useQuickReply(', false);
    }

    public function test_buyer_inbox_exposes_presence_channels_only_for_visible_conversations(): void
    {
        $buyer = User::factory()->create();
        $otherBuyer = User::factory()->create();
        $product = $this->product();
        $visibleConversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);
        $visibleConversation->messages()->create(['sender_id' => $buyer->id, 'type' => 'text', 'body' => 'Is this still available?']);
        $hiddenConversation = Conversation::create(['buyer_id' => $otherBuyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);
        $hiddenConversation->messages()->create(['sender_id' => $otherBuyer->id, 'type' => 'text', 'body' => 'Is this still available?']);

        Livewire::actingAs($buyer)
            ->test(Inbox::class)
            ->assertSeeHtml('data-presence-conversation="'.$visibleConversation->id.'"')
            ->assertDontSeeHtml('data-presence-conversation="'.$hiddenConversation->id.'"');
    }

    public function test_inbox_counts_only_unread_messages_from_the_other_participant(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);
        $conversation->messages()->create(['sender_id' => $seller->id, 'body' => 'First question']);
        $conversation->messages()->create(['sender_id' => $seller->id, 'body' => 'Second question']);
        $conversation->messages()->create(['sender_id' => $buyer->id, 'body' => 'My response']);

        Livewire::actingAs($buyer)->test(Inbox::class)->assertSee('2 unread messages');
        Livewire::actingAs($buyer)->test(Inbox::class)->set('filter', 'unread')->assertSee('2 unread messages');

        Livewire::actingAs($buyer)->test(Thread::class, ['conversation' => $conversation]);

        Livewire::actingAs($buyer)->test(Inbox::class)->assertDontSee('2 unread messages');
        Livewire::actingAs($buyer)->test(Inbox::class)->set('filter', 'unread')->assertSee('No matching conversations');
    }

    public function test_new_message_after_read_is_unread_even_within_the_same_second(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);
        $conversation->messages()->create(['sender_id' => $seller->id, 'body' => 'Read message']);

        Livewire::actingAs($buyer)->test(Thread::class, ['conversation' => $conversation]);
        $conversation->messages()->create(['sender_id' => $seller->id, 'body' => 'New message']);

        Livewire::actingAs($buyer)->test(Inbox::class)->assertSee('1 unread messages');
        Livewire::actingAs($buyer)->test(Inbox::class)->set('filter', 'unread')->assertSee('New message');
    }

    public function test_inactive_buyer_cannot_view_a_conversation(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now(), 'is_active' => false]);
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);

        $this->assertFalse(Gate::forUser($buyer)->allows('view', $conversation));
    }

    public function test_inactive_buyer_cannot_start_a_product_conversation(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now(), 'is_active' => false]);
        $product = $this->product();

        $this->actingAs($buyer)
            ->post(route('messages.products.start', $product))
            ->assertForbidden();

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_chat_history_keeps_a_bounded_window_while_older_messages_remain_accessible(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);
        $this->createMessages($conversation, $buyer, 201);

        $this->actingAs($buyer);

        Livewire::test(Thread::class, ['conversation' => $conversation])
            ->call('loadOlderMessages')
            ->call('loadOlderMessages')
            ->call('loadOlderMessages')
            ->call('loadOlderMessages')
            ->assertCount('messageIds', 200)
            ->assertSee('Message 0')
            ->assertSee('Load newer messages');
    }

    public function test_chat_history_can_return_to_the_latest_messages_after_loading_beyond_two_hundred(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);

        for ($index = 0; $index < 201; $index++) {
            $conversation->messages()->create([
                'sender_id' => $buyer->id,
                'body' => $index === 0 ? 'oldest-message-marker' : "Message {$index}",
                'created_at' => now()->addSeconds($index),
            ]);
        }

        $this->actingAs($buyer);

        Livewire::test(Thread::class, ['conversation' => $conversation])
            ->call('loadOlderMessages')
            ->call('loadOlderMessages')
            ->call('loadOlderMessages')
            ->call('loadOlderMessages')
            ->assertCount('messageIds', 200)
            ->assertSee('oldest-message-marker')
            ->assertSee('Load newer messages')
            ->call('jumpToLatest')
            ->assertSee('Message 200')
            ->assertDontSee('oldest-message-marker');
    }

    public function test_seller_replies_to_a_specific_product_inquiry(): void
    {
        $product = $this->product();
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $product->tenant_id, 'user_id' => $seller->id, 'role' => 'staff']);
        $buyer = User::factory()->create();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id]);
        $inquiry = $conversation->messages()->create([
            'sender_id' => $buyer->id, 'client_message_id' => (string) str()->uuid(), 'type' => 'text', 'body' => 'Is this in stock?',
            'product_id' => $product->id, 'context_snapshot' => ['type' => 'product', 'title' => $product->name, 'price' => $product->price],
        ]);

        Livewire::actingAs($seller)->test(Thread::class, ['conversation' => $conversation])
            ->call('replyTo', $inquiry->id)
            ->assertSee('Replying to '.$buyer->name)
            ->call('send', 'Yes, five left.', (string) str()->uuid())
            ->assertHasNoErrors()
            ->assertSet('replyToId', null)
            ->assertSee('data-reply-quote', false)
            ->assertDontSee('data-replying-to', false);

        $this->assertDatabaseHas('conversation_messages', ['body' => 'Yes, five left.', 'reply_to_id' => $inquiry->id]);
    }

    public function test_retrying_a_message_with_the_same_client_id_does_not_duplicate_or_rebroadcast_it(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);
        $clientMessageId = (string) str()->uuid();
        Event::fake([MessageSent::class, ConversationReceiptUpdated::class]);

        $this->actingAs($buyer);

        Livewire::test(Thread::class, ['conversation' => $conversation])
            ->set('clientMessageId', $clientMessageId)
            ->set('body', 'Is this available?')
            ->call('send')
            ->set('clientMessageId', $clientMessageId)
            ->set('body', 'Is this available?')
            ->call('send')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('conversation_messages', 1);
        Event::assertDispatchedTimes(MessageSent::class, 1);
    }

    public function test_opening_a_thread_persists_the_buyer_read_receipt(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);
        $message = $conversation->messages()->create(['sender_id' => $seller->id, 'body' => 'Your order is ready.']);
        Event::fake([ConversationReceiptUpdated::class]);

        $this->actingAs($buyer);

        Livewire::test(Thread::class, ['conversation' => $conversation]);

        $this->assertDatabaseHas('conversation_read_states', [
            'conversation_id' => $conversation->id,
            'user_id' => $buyer->id,
            'last_read_message_id' => $message->id,
            'last_delivered_message_id' => $message->id,
        ]);
        Event::assertDispatched(ConversationReceiptUpdated::class, fn (ConversationReceiptUpdated $event): bool => $event->event === 'message.delivered');
        Event::assertDispatched(ConversationReceiptUpdated::class, fn (ConversationReceiptUpdated $event): bool => $event->event === 'message.read');
    }

    public function test_a_conversation_participant_can_broadcast_a_typing_start(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);
        Event::fake([TypingStatusChanged::class]);

        Livewire::actingAs($buyer)
            ->test(Thread::class, ['conversation' => $conversation])
            ->call('startTyping')
            ->assertHasNoErrors();

        Event::assertDispatched(TypingStatusChanged::class, fn (TypingStatusChanged $event): bool => $event->conversationId === $conversation->id && $event->userId === $buyer->id && $event->typing,
        );
    }

    public function test_a_blocked_participant_cannot_broadcast_a_typing_start(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);
        $seller = User::factory()->seller()->create();
        TenantMember::create(['tenant_id' => $product->tenant_id, 'user_id' => $seller->id, 'role' => 'owner']);
        $component = Livewire::actingAs($buyer)->test(Thread::class, ['conversation' => $conversation]);

        // Blocked after opening the thread: they can still read it, but may no longer send.
        BlockedUser::create(['user_id' => $seller->id, 'blocked_user_id' => $buyer->id]);
        Event::fake([TypingStatusChanged::class]);

        $component->call('startTyping');

        Event::assertNotDispatched(TypingStatusChanged::class);
    }

    public function test_typing_checks_the_policy_once_per_broadcast_not_once_per_keystroke(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);
        Event::fake([TypingStatusChanged::class]);

        $component = Livewire::actingAs($buyer)->test(Thread::class, ['conversation' => $conversation]);

        $policyChecks = 0;
        DB::listen(function ($query) use (&$policyChecks): void {
            if (str_contains($query->sql, 'blocked_users')) {
                $policyChecks++;
            }
        });

        // Two seconds of typing at the 500ms debounce: the limiter allows one broadcast.
        foreach (range(1, 4) as $ignored) {
            $component->call('startTyping');
        }

        $this->assertSame(1, $policyChecks);
        Event::assertDispatchedTimes(TypingStatusChanged::class, 1);
    }

    public function test_mark_read_rechecks_conversation_access(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);
        $conversation->messages()->create(['sender_id' => $seller->id, 'body' => 'New message']);
        $this->actingAs($buyer);
        $thread = Livewire::test(Thread::class, ['conversation' => $conversation]);
        ConversationReadState::query()->where('conversation_id', $conversation->id)->where('user_id', $buyer->id)->delete();
        $buyer->update(['is_active' => false]);

        $thread->call('markRead')->assertNotFound();
    }

    public function test_sending_an_older_message_does_not_move_inbox_timestamp_backwards(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);
        $latestMessageAt = now()->setDate(2030, 1, 2);
        $conversation->forceFill(['latest_message_at' => $latestMessageAt])->saveQuietly();
        $this->travelTo(now()->setDate(2030, 1, 1));

        app(SendConversationMessage::class)->handle($conversation, $buyer, 'Older timestamp', (string) str()->uuid());

        $this->assertSame($latestMessageAt->toDateTimeString(), $conversation->refresh()->latest_message_at->toDateTimeString());
    }

    public function test_a_received_message_moves_its_conversation_to_the_top_within_the_same_second(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();
        $firstProduct = $this->product();
        $secondProduct = $this->product();
        $firstConversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $firstProduct->store_id, 'product_id' => $firstProduct->id]);
        $secondConversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $secondProduct->store_id, 'product_id' => $secondProduct->id]);
        Event::fake([ConversationInboxChanged::class, MessageSent::class]);
        $timestamp = now()->startOfSecond();
        $sendMessage = app(SendConversationMessage::class);
        $inbox = Livewire::actingAs($buyer)->test(Inbox::class);

        $this->travelTo($timestamp->copy()->addMicrosecond());
        $sendMessage->handle($secondConversation, $seller, 'Earlier conversation reply', (string) str()->uuid());
        $this->travelTo($timestamp->copy()->addMilliseconds(200));
        $sendMessage->handle($firstConversation, $seller, 'Newest conversation reply', (string) str()->uuid());

        $inbox->call('$refresh')
            ->assertSeeInOrder(['Newest conversation reply', 'Earlier conversation reply']);
    }

    public function test_blocking_a_seller_prevents_new_messages_from_both_participants(): void
    {
        $buyer = User::factory()->create();
        $owner = User::factory()->seller()->create();
        $product = $this->product();
        TenantMember::create(['tenant_id' => $product->tenant_id, 'user_id' => $owner->id, 'role' => 'owner']);
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);

        $this->actingAs($buyer)->post(route('messages.block', $conversation))->assertRedirect(route('messages.index'));

        $this->assertDatabaseHas('blocked_users', ['user_id' => $buyer->id, 'blocked_user_id' => $owner->id]);
        $this->assertFalse(Gate::forUser($buyer)->allows('send', $conversation));
        $this->assertFalse(Gate::forUser($owner)->allows('send', $conversation));
    }

    public function test_buyer_can_report_only_another_participants_message(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);
        $message = $conversation->messages()->create(['sender_id' => $seller->id, 'body' => 'Reported message']);

        $this->actingAs($buyer)->post(route('messages.report', [$conversation, $message]), ['reason' => 'Spam', 'details' => 'Repeated links.'])->assertRedirect();

        $this->assertDatabaseHas('message_reports', ['conversation_message_id' => $message->id, 'reporter_id' => $buyer->id, 'reason' => 'Spam', 'status' => 'pending']);
    }

    public function test_attachment_download_requires_conversation_access(): void
    {
        Storage::fake('chat_attachments');
        $buyer = User::factory()->create();
        $otherBuyer = User::factory()->create();
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);
        $message = $conversation->messages()->create(['sender_id' => $buyer->id, 'body' => 'question.pdf', 'type' => 'file']);
        $path = 'chat/question.pdf';
        Storage::disk('chat_attachments')->put($path, 'file contents');
        $attachment = $message->attachments()->create(['uploaded_by' => $buyer->id, 'disk' => 'chat_attachments', 'path' => $path, 'original_name' => 'question.pdf', 'mime_type' => 'application/pdf', 'byte_size' => 13]);

        $this->actingAs($otherBuyer)->get(route('messages.attachments.show', $attachment))->assertNotFound();
        $this->actingAs($buyer)
            ->get(route('messages.attachments.show', $attachment))
            ->assertOk()
            ->assertHeaderContains('Content-Disposition', 'attachment');
        $this->get(route('messages.attachments.show', $attachment).'?preview=1')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeaderContains('Content-Disposition', 'inline');
    }

    public function test_image_attachments_render_as_previews_and_are_served_inline(): void
    {
        Storage::fake('chat_attachments');
        $buyer = User::factory()->create();
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);
        $message = $conversation->messages()->create(['sender_id' => $buyer->id, 'body' => 'sales.png', 'type' => 'file']);
        $path = 'chat/sales.png';
        Storage::disk('chat_attachments')->put($path, 'image contents');
        $attachment = $message->attachments()->create(['uploaded_by' => $buyer->id, 'disk' => 'chat_attachments', 'path' => $path, 'original_name' => 'sales.png', 'mime_type' => 'image/png', 'byte_size' => 14]);
        $attachmentUrl = route('messages.attachments.show', $attachment);

        Livewire::actingAs($buyer)
            ->test(Thread::class, ['conversation' => $conversation])
            ->assertSeeHtml('<img src="'.$attachmentUrl.'" alt="sales.png"')
            ->assertSeeHtml('<a href="'.$attachmentUrl.'" download="sales.png"');

        $this->actingAs($buyer)
            ->get($attachmentUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeaderContains('Content-Disposition', 'inline')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_uploading_a_valid_attachment_creates_one_private_file_message_on_retry(): void
    {
        Storage::fake('chat_attachments');
        $buyer = User::factory()->create();
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);
        $clientMessageId = (string) str()->uuid();
        $payload = ['client_message_id' => $clientMessageId, 'attachment' => UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF")];

        $this->actingAs($buyer)->post(route('messages.attachments.store', $conversation), $payload)->assertRedirect();
        $this->post(route('messages.attachments.store', $conversation), $payload)->assertRedirect();

        $this->assertDatabaseCount('conversation_messages', 1);
        $this->assertDatabaseCount('message_attachments', 1);
        $this->assertSame('', $conversation->messages()->first()->body);
        $this->assertSame('receipt.pdf', $conversation->messages()->first()->attachments()->first()->original_name);
        $attachment = $conversation->messages()->first()->attachments()->first();
        Storage::disk('chat_attachments')->assertExists($attachment->path);
    }

    public function test_sending_an_attachment_with_text_creates_one_message_with_a_caption(): void
    {
        Storage::fake('chat_attachments');
        $buyer = User::factory()->create();
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);

        $this->actingAs($buyer)->post(route('messages.attachments.store', $conversation), [
            'client_message_id' => (string) str()->uuid(),
            'body' => 'Please check this receipt.',
            'attachment' => UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF"),
        ])->assertRedirect();

        $this->assertDatabaseCount('conversation_messages', 1);
        $message = $conversation->messages()->first();
        $this->assertSame('Please check this receipt.', $message->body);
        $this->assertSame('file', $message->type);
        $this->assertSame('receipt.pdf', $message->attachments()->first()->original_name);
        Livewire::actingAs($buyer)->test(Thread::class, ['conversation' => $conversation])->assertSee('Please check this receipt.');
    }

    public function test_buyer_cannot_upload_to_another_buyers_conversation(): void
    {
        Storage::fake('chat_attachments');
        $buyer = User::factory()->create();
        $otherBuyer = User::factory()->create();
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);

        $this->actingAs($otherBuyer)->post(route('messages.attachments.store', $conversation), [
            'client_message_id' => (string) str()->uuid(),
            'attachment' => UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n%%EOF"),
        ])->assertNotFound();

        $this->assertDatabaseCount('conversation_messages', 0);
        $this->assertDatabaseCount('message_attachments', 0);
    }

    public function test_attachment_text_must_fit_the_message_limit(): void
    {
        Storage::fake('chat_attachments');
        $buyer = User::factory()->create();
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);

        $this->actingAs($buyer)->post(route('messages.attachments.store', $conversation), [
            'client_message_id' => (string) str()->uuid(),
            'body' => str_repeat('a', 2001),
            'attachment' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'),
        ])->assertSessionHasErrors('body');

        $this->assertDatabaseCount('conversation_messages', 0);
        $this->assertDatabaseCount('message_attachments', 0);
    }

    public function test_attachment_upload_rejects_unsupported_or_disguised_files(): void
    {
        Storage::fake('chat_attachments');
        $buyer = User::factory()->create();
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);

        foreach (['notes.docx', 'table.xlsx', 'notes.txt', 'disguised.png', 'broken.pdf'] as $name) {
            $content = $name === 'broken.pdf' ? 'This is not a PDF' : "%PDF-1.4\n%%EOF";
            $this->actingAs($buyer)->post(route('messages.attachments.store', $conversation), [
                'client_message_id' => (string) str()->uuid(),
                'attachment' => UploadedFile::fake()->createWithContent($name, $content),
            ])->assertSessionHasErrors('attachment');
        }

        $this->assertDatabaseCount('conversation_messages', 0);
        $this->assertDatabaseCount('message_attachments', 0);
    }

    public function test_attachment_upload_rejects_files_over_five_megabytes(): void
    {
        Storage::fake('chat_attachments');
        $buyer = User::factory()->create();
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);

        $this->actingAs($buyer)->post(route('messages.attachments.store', $conversation), [
            'client_message_id' => (string) str()->uuid(),
            'attachment' => UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf'),
        ])->assertSessionHasErrors('attachment');

        $this->assertDatabaseCount('message_attachments', 0);
    }

    public function test_user_broadcast_channel_authorization_compares_ulid_ids_as_strings(): void
    {
        config(['broadcasting.default' => 'reverb']);
        Broadcast::forgetDrivers();
        require base_path('routes/channels.php');
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $payload = ['socket_id' => '123.456', 'channel_name' => 'private-App.Models.User.'.$otherUser->id];

        $this->actingAs($user)->postJson('/broadcasting/auth', $payload)->assertForbidden();
    }

    public function test_conversation_presence_channel_authorizes_participants_only(): void
    {
        config(['broadcasting.default' => 'reverb']);
        Broadcast::forgetDrivers();
        require base_path('routes/channels.php');
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();
        $otherBuyer = User::factory()->create();
        $product = $this->product();
        TenantMember::create(['tenant_id' => $product->tenant_id, 'user_id' => $seller->id, 'role' => 'staff']);
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);
        $payload = ['socket_id' => '123.456', 'channel_name' => 'presence-conversation.'.$conversation->id];
        $this->actingAs($buyer)->postJson('/broadcasting/auth', $payload)->assertOk();
        $this->actingAs($seller)->postJson('/broadcasting/auth', $payload)->assertOk();
        $this->actingAs($otherBuyer)->postJson('/broadcasting/auth', $payload)->assertForbidden();
    }

    public function test_seller_can_only_view_conversations_for_an_active_store_membership(): void
    {
        $product = $this->product();
        $buyer = User::factory()->create();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);
        $seller = User::factory()->seller()->create();
        TenantMember::create(['tenant_id' => $product->tenant_id, 'user_id' => $seller->id, 'role' => 'staff']);
        $otherSeller = User::factory()->seller()->create();
        $otherTenant = Tenant::create(['name' => 'Other tenant', 'slug' => 'other-tenant-'.str()->lower(str()->random(8)), 'status' => 'active']);
        TenantMember::create(['tenant_id' => $otherTenant->id, 'user_id' => $otherSeller->id, 'role' => 'staff']);

        $this->assertTrue(Gate::forUser($seller)->allows('view', $conversation));
        $this->assertFalse(Gate::forUser($otherSeller)->allows('view', $conversation));
    }

    public function test_message_rejects_context_from_another_store(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();
        $otherProduct = $this->product();

        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id]);

        $this->expectException(NotFoundHttpException::class);
        app(SendConversationMessage::class)->handle($conversation, $buyer, 'Question', (string) str()->uuid(), 'text', $otherProduct->id);
    }

    public function test_inbox_search_treats_like_wildcards_as_literal_text(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();
        $product->store->forceFill(['name' => '50% off store'])->save();
        $otherProduct = $this->product();
        $otherProduct->store->forceFill(['name' => '50 off store'])->save();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);
        $conversation->messages()->create(['sender_id' => $buyer->id, 'type' => 'text', 'body' => 'Is this still available?']);
        $otherConversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $otherProduct->store_id, 'product_id' => $otherProduct->id]);
        $otherConversation->messages()->create(['sender_id' => $buyer->id, 'type' => 'text', 'body' => 'Is this still available?']);

        $this->actingAs($buyer);

        Livewire::test(Inbox::class)
            ->set('search', '50% off')
            ->assertSee('50% off store')
            ->assertDontSee('50 off store');
    }

    public function test_unblocking_restores_messaging_for_both_participants(): void
    {
        $buyer = User::factory()->create();
        $owner = User::factory()->seller()->create();
        $product = $this->product();
        TenantMember::create(['tenant_id' => $product->tenant_id, 'user_id' => $owner->id, 'role' => 'owner']);
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);
        $this->actingAs($buyer)->post(route('messages.block', $conversation));

        $this->actingAs($buyer)->delete(route('messages.unblock', $conversation))->assertRedirect();

        $this->assertDatabaseCount('blocked_users', 0);
        $this->assertTrue(Gate::forUser($buyer)->allows('send', $conversation));
        $this->assertTrue(Gate::forUser($owner)->allows('send', $conversation));
    }

    public function test_a_participant_cannot_lift_a_block_the_other_side_created(): void
    {
        $buyer = User::factory()->create();
        $owner = User::factory()->seller()->create();
        $product = $this->product();
        TenantMember::create(['tenant_id' => $product->tenant_id, 'user_id' => $owner->id, 'role' => 'owner']);
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);
        $this->actingAs($owner)->post(route('messages.block', $conversation));

        $this->actingAs($buyer)->delete(route('messages.unblock', $conversation))->assertRedirect();

        $this->assertDatabaseHas('blocked_users', ['user_id' => $owner->id, 'blocked_user_id' => $buyer->id]);
        $this->assertFalse(Gate::forUser($buyer)->allows('send', $conversation));
    }

    public function test_sending_is_rate_limited_per_user(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);

        $this->actingAs($buyer);
        $component = Livewire::test(Thread::class, ['conversation' => $conversation]);

        for ($index = 0; $index < 20; $index++) {
            $component->set('clientMessageId', (string) str()->uuid())
                ->set('body', "Message {$index}")
                ->call('send')
                ->assertHasNoErrors();
        }

        $component->set('clientMessageId', (string) str()->uuid())
            ->set('body', 'One too many')
            ->call('send')
            ->assertHasErrors('body');

        $this->assertDatabaseCount('conversation_messages', 20);
    }

    public function test_a_client_message_id_cannot_be_replayed_into_another_conversation(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();
        $otherProduct = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);
        $otherConversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $otherProduct->store_id, 'product_id' => $otherProduct->id]);
        $clientMessageId = (string) str()->uuid();

        $sendMessage = app(SendConversationMessage::class);
        $sendMessage->handle($conversation, $buyer, 'Is this available?', $clientMessageId);

        $this->expectException(ValidationException::class);
        $sendMessage->handle($otherConversation, $buyer, 'Is this available?', $clientMessageId);
    }

    public function test_inbox_pager_renders_past_the_first_page_of_conversations(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);

        for ($index = 0; $index < 21; $index++) {
            $product = $this->product();
            $conversation = Conversation::create([
                'buyer_id' => $buyer->id,
                'store_id' => $product->store_id,
                'product_id' => $product->id,
                'latest_message_at' => now()->subMinutes($index),
            ]);
            $conversation->messages()->create(['sender_id' => $buyer->id, 'type' => 'text', 'body' => 'Is this still available?']);
        }

        Livewire::actingAs($buyer)->test(Inbox::class)
            ->assertOk()
            ->assertSee('Pagination Navigation')
            ->assertSee('Page 1')
            ->assertDontSee('Showing');
    }

    private function product(): Product
    {
        $suffix = str()->lower(str()->random(8));
        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => "Store {$suffix}", 'slug' => "store-{$suffix}", 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);

        return Product::create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'name' => "Product {$suffix}", 'slug' => "product-{$suffix}", 'sku' => "SKU-{$suffix}", 'price' => 199, 'stock_quantity' => 3, 'status' => 'active']);
    }

    private function createMessages(Conversation $conversation, User $sender, int $count): void
    {
        for ($index = 0; $index < $count; $index++) {
            $conversation->messages()->create([
                'sender_id' => $sender->id,
                'body' => "Message {$index}",
                'created_at' => now()->addSeconds($index),
            ]);
        }
    }
}
