<?php

namespace Tests\Feature;

use App\Actions\MarkConversationRead;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConversationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_told_to_sign_in(): void
    {
        $this->getJson('/api/v1/conversations')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'auth.unauthenticated');
    }

    public function test_the_inbox_lists_only_the_buyers_chats_with_an_unread_count(): void
    {
        [$buyer, $seller, $conversation] = $this->conversation();
        $conversation->messages()->create([
            'sender_id' => $seller->id,
            'client_message_id' => (string) Str::uuid(),
            'type' => 'text',
            'body' => 'Is this still available?',
        ]);

        $this->actingAs($buyer)
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $conversation->id)
            ->assertJsonPath('data.0.unread_count', 1)
            ->assertJsonPath('data.0.latest_message.body', 'Is this still available?')
            ->assertJsonPath('data.0.store.slug', $conversation->store->slug);

        // A seller account only sees the chats it is buying in, not its store's inbox.
        $seller->update(['account_type' => 'seller']);

        $this->actingAs($seller)
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_buyer_reads_a_page_of_messages_oldest_first(): void
    {
        [$buyer, $seller, $conversation] = $this->conversation();

        foreach (['First', 'Second'] as $body) {
            $conversation->messages()->create([
                'sender_id' => $seller->id,
                'client_message_id' => (string) Str::uuid(),
                'type' => 'text',
                'body' => $body,
            ]);
        }

        $this->actingAs($buyer)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.body', 'First')
            ->assertJsonPath('data.1.body', 'Second')
            ->assertJsonPath('conversation.id', $conversation->id);
    }

    public function test_sending_a_message_is_idempotent_and_owner_only(): void
    {
        [$buyer, , $conversation] = $this->conversation();
        $payload = ['body' => 'Hello there', 'client_message_id' => (string) Str::uuid()];

        $first = $this->actingAs($buyer)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", $payload)
            ->assertCreated()
            ->assertJsonPath('data.is_mine', true)
            ->assertJsonPath('data.body', 'Hello there')
            ->assertJsonPath('data.client_message_id', $payload['client_message_id']);

        $this->actingAs($buyer)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertDatabaseCount('conversation_messages', 1);

        $otherBuyer = User::factory()->create();

        $this->actingAs($otherBuyer)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'body' => 'Not mine',
                'client_message_id' => (string) Str::uuid(),
            ])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'resource.not_found');
    }

    public function test_starting_a_product_conversation_returns_the_existing_one_on_repeat(): void
    {
        [$buyer, , , $store, $product] = $this->conversation();
        $fresh = Product::create([
            'tenant_id' => $product->tenant_id,
            'store_id' => $store->id,
            'name' => 'Second Chat Tote',
            'slug' => 'second-chat-tote-'.str()->lower(str()->random(8)),
            'sku' => 'SECOND-CHAT-TOTE-'.str()->upper(str()->random(8)),
            'price' => '199.00',
            'stock_quantity' => 5,
            'status' => 'active',
        ]);

        $first = $this->actingAs($buyer)
            ->postJson('/api/v1/conversations/start', ['product_id' => $fresh->id])
            ->assertOk()
            ->assertJsonPath('data.pending_context.product_id', $fresh->id);

        $this->actingAs($buyer)
            ->postJson('/api/v1/conversations/start', ['product_id' => $fresh->id])
            ->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_starting_a_conversation_without_sending_a_message_is_not_listed(): void
    {
        [$buyer, , , $store, $product] = $this->conversation();
        $fresh = Product::create([
            'tenant_id' => $product->tenant_id,
            'store_id' => $store->id,
            'name' => 'Unmessaged Tote',
            'slug' => 'unmessaged-tote-'.str()->lower(str()->random(8)),
            'sku' => 'UNMESSAGED-TOTE-'.str()->upper(str()->random(8)),
            'price' => '199.00',
            'stock_quantity' => 5,
            'status' => 'active',
        ]);

        $this->actingAs($buyer)
            ->postJson('/api/v1/conversations/start', ['product_id' => $fresh->id])
            ->assertOk();

        $this->actingAs($buyer)
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_reading_a_thread_advances_the_receipt_and_clears_unread(): void
    {
        [$buyer, $seller, $conversation] = $this->conversation();
        $message = $conversation->messages()->create([
            'sender_id' => $seller->id,
            'client_message_id' => (string) Str::uuid(),
            'type' => 'text',
            'body' => 'Packed and ready.',
        ]);

        $this->actingAs($buyer)
            ->postJson("/api/v1/conversations/{$conversation->id}/read")
            ->assertOk()
            ->assertJsonPath('data.last_message_id', $message->id)
            ->assertJsonPath('data.receipts', ['message.delivered', 'message.read']);

        $this->assertDatabaseHas('conversation_read_states', [
            'conversation_id' => $conversation->id,
            'user_id' => $buyer->id,
            'last_read_message_id' => $message->id,
        ]);

        $this->actingAs($buyer)
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.unread_count', 0);
    }

    public function test_marking_a_thread_read_is_rate_limited(): void
    {
        [$buyer, , $conversation] = $this->conversation();

        for ($index = 0; $index < 60; $index++) {
            $this->actingAs($buyer)
                ->postJson("/api/v1/conversations/{$conversation->id}/read")
                ->assertOk();
        }

        $this->actingAs($buyer)
            ->postJson("/api/v1/conversations/{$conversation->id}/read")
            ->assertTooManyRequests();

        $this->actingAs($buyer)
            ->getJson('/api/v1/conversations')
            ->assertOk();
    }

    public function test_an_attachment_is_served_through_a_signed_url(): void
    {
        Storage::fake('chat_attachments');
        [$buyer, , $conversation] = $this->conversation();

        $response = $this->actingAs($buyer)
            ->post(
                "/api/v1/conversations/{$conversation->id}/attachments",
                [
                    'attachment' => UploadedFile::fake()->image('receipt.jpg', 20, 20),
                    'client_message_id' => (string) Str::uuid(),
                    'body' => 'Here is the receipt.',
                ],
                ['Accept' => 'application/json'],
            )
            ->assertCreated()
            ->assertJsonPath('data.type', 'file')
            ->assertJsonPath('data.attachments.0.name', 'receipt.jpg');

        $url = $response->json('data.attachments.0.url');
        $this->assertIsString($url);

        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->get($url.'tampered')->assertForbidden();
    }

    public function test_signed_attachment_urls_validate_behind_a_tls_terminating_proxy(): void
    {
        Storage::fake('chat_attachments');

        // Production signs media URLs over https because APP_URL is https.
        URL::forceHttps();

        [$buyer, , $conversation] = $this->conversation();

        $url = $this->actingAs($buyer)
            ->postJson(
                "/api/v1/conversations/{$conversation->id}/attachments",
                [
                    'attachment' => UploadedFile::fake()->image('receipt.jpg', 20, 20),
                    'client_message_id' => (string) Str::uuid(),
                ],
            )
            ->assertCreated()
            ->json('data.attachments.0.url');

        $this->assertIsString($url);
        $this->assertStringStartsWith('https://', $url);

        // The test client builds URLs through url(), which would force https and
        // hide a broken proxy trust. Build the plain-HTTP request by hand instead,
        // exactly as Apache receives it after Cloudflare and Render terminate TLS.
        $parts = parse_url($url);
        $authority = $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $path = $parts['path'].'?'.$parts['query'];

        $request = Request::create('http://'.$authority.$path, 'GET', server: [
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
    }

    public function test_attachment_urls_stay_stable_between_message_reads(): void
    {
        Storage::fake('chat_attachments');
        $this->travelTo('2026-11-03 13:15:00');
        [$buyer, , $conversation] = $this->conversation();

        $this->actingAs($buyer)
            ->post(
                "/api/v1/conversations/{$conversation->id}/attachments",
                [
                    'attachment' => UploadedFile::fake()->image('receipt.jpg', 20, 20),
                    'client_message_id' => (string) Str::uuid(),
                ],
                ['Accept' => 'application/json'],
            )
            ->assertCreated();

        $first = $this->actingAs($buyer)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->json('data.0.attachments.0.url');
        $second = $this->actingAs($buyer)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->json('data.0.attachments.0.url');

        $this->assertIsString($first);
        $this->assertSame($first, $second);

        $this->travelBack();
    }

    public function test_a_pdf_attachment_signed_url_opens_inline_for_mobile_viewers(): void
    {
        Storage::fake('chat_attachments');
        [$buyer, , $conversation] = $this->conversation();

        $response = $this->actingAs($buyer)
            ->post(
                "/api/v1/conversations/{$conversation->id}/attachments",
                [
                    'attachment' => UploadedFile::fake()->createWithContent(
                        'receipt.pdf',
                        "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF",
                    ),
                    'client_message_id' => (string) Str::uuid(),
                ],
                ['Accept' => 'application/json'],
            )
            ->assertCreated()
            ->assertJsonPath('data.attachments.0.mime_type', 'application/pdf')
            ->assertJsonPath('data.attachments.0.is_image', false);

        $url = $response->json('data.attachments.0.url');
        $this->assertIsString($url);
        $this->assertStringContainsString('preview=1', $url);

        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeaderContains('Content-Disposition', 'inline');

        $this->get($url.'tampered')->assertForbidden();
    }

    public function test_a_signed_attachment_url_returns_not_found_when_the_stored_file_is_missing(): void
    {
        Storage::fake('chat_attachments');
        [$buyer, $seller, $conversation] = $this->conversation();

        $message = $conversation->messages()->create([
            'sender_id' => $seller->id,
            'client_message_id' => (string) Str::uuid(),
            'type' => 'file',
            'body' => '',
        ]);
        $message->attachments()->create([
            'uploaded_by' => $seller->id,
            'disk' => 'chat_attachments',
            'path' => 'chat/missing.pdf',
            'original_name' => 'missing.pdf',
            'mime_type' => 'application/pdf',
            'byte_size' => 12,
            'status' => 'available',
        ]);

        $url = $this->actingAs($buyer)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->json('data.0.attachments.0.url');

        $this->assertIsString($url);
        $this->get($url)->assertNotFound();
    }

    public function test_an_authenticated_buyer_can_join_a_private_channel(): void
    {
        config()->set('broadcasting.default', 'reverb');
        // Channels are registered against the connection that was default when the app
        // booted; re-register them on Reverb after switching for this test.
        require base_path('routes/channels.php');

        [$buyer, , $conversation] = $this->conversation();

        $this->assertTrue($buyer->can('view', $conversation));

        $this->actingAs($buyer)
            ->postJson('/api/v1/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-conversation.'.$conversation->id,
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);

        $otherBuyer = User::factory()->create();

        $this->actingAs($otherBuyer)
            ->postJson('/api/v1/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-conversation.'.$conversation->id,
            ])
            ->assertForbidden();
    }

    public function test_messages_response_includes_read_receipt_on_last_outgoing_message(): void
    {
        [$buyer, $seller, $conversation] = $this->conversation();

        $buyerMessage = $conversation->messages()->create([
            'sender_id' => $buyer->id,
            'client_message_id' => (string) Str::uuid(),
            'type' => 'text',
            'body' => 'Is this still available?',
        ]);

        // Seller has read up to the buyer's last message.
        app(MarkConversationRead::class)->handle($conversation, $seller);

        $data = $this->actingAs($buyer)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->json('data');

        $outgoing = collect($data)->firstWhere('is_mine', true);
        $this->assertSame('Read', $outgoing['receipt']);

        // After the seller replies, the buyer's outgoing message still shows Read.
        // The incoming reply must not carry a receipt field.
        $conversation->messages()->create([
            'sender_id' => $seller->id,
            'client_message_id' => (string) Str::uuid(),
            'type' => 'text',
            'body' => 'Yes!',
        ]);

        $data = $this->actingAs($buyer)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->json('data');

        $incoming = collect($data)->firstWhere('is_mine', false);
        $this->assertArrayNotHasKey('receipt', $incoming);

        $outgoing = collect($data)->firstWhere('is_mine', true);
        $this->assertSame('Read', $outgoing['receipt']);
    }

    public function test_messages_response_shows_sent_when_recipient_has_not_read(): void
    {
        [$buyer, , $conversation] = $this->conversation();

        $conversation->messages()->create([
            'sender_id' => $buyer->id,
            'client_message_id' => (string) Str::uuid(),
            'type' => 'text',
            'body' => 'Hello?',
        ]);

        $data = $this->actingAs($buyer)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->json('data');

        $outgoing = collect($data)->firstWhere('is_mine', true);
        $this->assertSame('Sent', $outgoing['receipt']);
    }

    public function test_multiple_product_inquiries_share_one_room_and_keep_message_snapshots(): void
    {
        [$buyer, , $conversation, $store, $firstProduct] = $this->conversation();
        $secondProduct = Product::create([
            'tenant_id' => $firstProduct->tenant_id, 'store_id' => $store->id,
            'name' => 'Second item', 'slug' => 'second-item-'.str()->random(8),
            'sku' => 'SECOND-'.str()->random(8), 'price' => '850.00',
            'stock_quantity' => 2, 'status' => 'active',
        ]);
        $firstId = (string) Str::uuid();
        $secondId = (string) Str::uuid();

        $this->actingAs($buyer)->postJson('/api/v1/conversations/start', ['product_id' => $firstProduct->id])->assertOk();
        $this->postJson('/api/v1/conversations/start', ['product_id' => $secondProduct->id])->assertOk()
            ->assertJsonPath('data.id', $conversation->id);
        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'First inquiry', 'client_message_id' => $firstId, 'product_id' => $firstProduct->id,
        ])->assertCreated();
        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Second inquiry', 'client_message_id' => $secondId, 'product_id' => $secondProduct->id,
        ])->assertCreated()->assertJsonPath('data.context_snapshot.price', '850.00');
        $secondProduct->update(['name' => 'Renamed item', 'price' => '999.00']);

        $this->getJson("/api/v1/conversations/{$conversation->id}/messages")->assertOk()
            ->assertJsonPath('data.0.context_snapshot.title', 'Chat Tote')
            ->assertJsonPath('data.1.context_snapshot.title', 'Second item')
            ->assertJsonPath('data.1.context_snapshot.price', '850.00');
        $this->getJson('/api/v1/conversations?filter=product')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.latest_message.body', 'Second inquiry');
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_product_and_order_inquiries_share_one_room_with_an_order_card(): void
    {
        [$buyer, , $conversation, $store, $product] = $this->conversation();
        $order = Order::create([
            'user_id' => $buyer->id, 'number' => 'JM-1042', 'status' => 'pending',
            'recipient_name' => 'Buyer', 'phone' => '+639171234567',
            'address_line' => '10 Market Street', 'city' => 'Manila',
            'subtotal' => '199.00', 'shipping_amount' => '0.00', 'total' => '199.00',
        ]);
        $sellerOrder = $order->sellerOrders()->create([
            'tenant_id' => $product->tenant_id, 'store_id' => $store->id,
            'status' => 'pending', 'subtotal' => '199.00', 'shipping_amount' => '0.00', 'total' => '199.00',
        ]);
        $order->payment()->create(['method' => 'cod', 'status' => 'pending', 'amount' => '199.00']);
        $product->update(['free_shipping' => true]);
        foreach ([['Rice cooker', 'products/rice-cooker.jpg', 'White', 1, 5], ['Spare lid', null, null, 2, 4]] as [$name, $imagePath, $variant, $quantity, $rating]) {
            $item = $sellerOrder->items()->create([
                'product_id' => $product->id, 'product_name' => $name, 'sku' => 'SKU-'.str()->random(6),
                'variant_label' => $variant, 'unit_price' => '66.33', 'quantity' => $quantity,
                'line_total' => '99.50', 'image_path' => $imagePath,
            ]);
            ProductReview::create([
                'product_id' => $product->id, 'order_item_id' => $item->id, 'user_id' => $buyer->id,
                'rating' => $rating, 'title' => 'Review', 'body' => 'Works well.', 'status' => 'published',
            ]);
        }

        $this->actingAs($buyer)->postJson('/api/v1/conversations/start', ['seller_order_id' => $sellerOrder->id])
            ->assertOk()->assertJsonPath('data.id', $conversation->id);
        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'When will this ship?', 'client_message_id' => (string) Str::uuid(),
            'seller_order_id' => $sellerOrder->id,
        ])->assertCreated()
            ->assertJsonPath('data.context_snapshot.number', 'JM-1042')
            ->assertJsonPath('data.context_snapshot.total', '199.00')
            ->assertJsonPath('data.context_snapshot.item_count', 3)
            ->assertJsonPath('data.context_snapshot.payment_method', 'cod')
            ->assertJsonPath('data.context_snapshot.city', 'Manila')
            ->assertJsonPath('data.context_snapshot.image_url', Storage::disk('product_images')->url('products/rice-cooker.jpg'))
            ->assertJsonPath('data.context_snapshot.items', [
                ['name' => 'Rice cooker', 'variant' => 'White', 'quantity' => 1, 'image_path' => 'products/rice-cooker.jpg', 'rating' => 4.5, 'rating_count' => 2, 'sold' => 3, 'free_shipping' => true, 'image_url' => Storage::disk('product_images')->url('products/rice-cooker.jpg')],
                ['name' => 'Spare lid', 'variant' => null, 'quantity' => 2, 'image_path' => null, 'rating' => 4.5, 'rating_count' => 2, 'sold' => 3, 'free_shipping' => true, 'image_url' => null],
            ]);
        $this->getJson('/api/v1/conversations?filter=order')->assertOk()
            ->assertJsonCount(1, 'data');
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_message_context_is_rejected_across_stores_and_duplicate_ids_cannot_change_context(): void
    {
        [$buyer, , $conversation, , $product] = $this->conversation();
        $otherTenant = Tenant::create(['name' => 'Other tenant', 'slug' => 'other-tenant-'.str()->random(8), 'status' => 'active']);
        $otherStore = Store::create([
            'tenant_id' => $otherTenant->id, 'name' => 'Other shop',
            'slug' => 'other-shop-'.str()->random(8), 'phone' => '+639171234567',
            'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active',
        ]);
        $otherProduct = Product::create([
            'tenant_id' => $otherTenant->id, 'store_id' => $otherStore->id,
            'name' => 'Other item', 'slug' => 'other-item-'.str()->random(8),
            'sku' => 'OTHER-'.str()->random(8), 'price' => '10.00',
            'stock_quantity' => 1, 'status' => 'active',
        ]);
        $clientId = (string) Str::uuid();
        $this->actingAs($buyer)->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Question', 'client_message_id' => $clientId, 'product_id' => $otherProduct->id,
        ])->assertNotFound();
        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Question', 'client_message_id' => $clientId, 'product_id' => $product->id,
        ])->assertCreated();
        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Question', 'client_message_id' => $clientId,
        ])->assertUnprocessable();
        $this->assertDatabaseCount('conversation_messages', 1);
    }

    public function test_a_reply_quotes_a_message_from_the_same_conversation_only(): void
    {
        [$buyer, $seller, $conversation, , $product] = $this->conversation();
        $sellerMessage = $conversation->messages()->create([
            'sender_id' => $seller->id, 'client_message_id' => (string) Str::uuid(),
            'type' => 'text', 'body' => 'Yes, the tote is available.',
            'product_id' => $product->id, 'context_snapshot' => ['type' => 'product', 'title' => 'Chat Tote', 'price' => '199.00'],
        ]);

        $this->actingAs($buyer)->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Great, any other colors?', 'client_message_id' => (string) Str::uuid(), 'reply_to_id' => $sellerMessage->id,
        ])->assertCreated()
            ->assertJsonPath('data.reply_to.id', $sellerMessage->id)
            ->assertJsonPath('data.reply_to.context_label', 'Chat Tote')
            ->assertJsonPath('data.reply_to.body', 'Yes, the tote is available.')
            ->assertJsonPath('data.reply_to.is_mine', false);
        $this->getJson("/api/v1/conversations/{$conversation->id}/messages")->assertOk()
            ->assertJsonPath('data.0.reply_to', null)
            ->assertJsonPath('data.1.reply_to.id', $sellerMessage->id);

        [, $otherSeller, $otherConversation] = $this->conversation();
        $foreignMessage = $otherConversation->messages()->create([
            'sender_id' => $otherSeller->id, 'client_message_id' => (string) Str::uuid(), 'type' => 'text', 'body' => 'Elsewhere',
        ]);
        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Sneaky', 'client_message_id' => (string) Str::uuid(), 'reply_to_id' => $foreignMessage->id,
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('conversation_messages', ['body' => 'Sneaky']);
    }

    public function test_the_buyer_reports_only_the_other_participants_message_once(): void
    {
        [$buyer, $seller, $conversation] = $this->conversation();
        $sellerMessage = $conversation->messages()->create([
            'sender_id' => $seller->id, 'client_message_id' => (string) Str::uuid(), 'type' => 'text', 'body' => 'Buy outside the app',
        ]);
        $ownMessage = $conversation->messages()->create([
            'sender_id' => $buyer->id, 'client_message_id' => (string) Str::uuid(), 'type' => 'text', 'body' => 'Mine',
        ]);
        $url = "/api/v1/conversations/{$conversation->id}/messages/{$sellerMessage->id}/report";

        $this->actingAs($buyer)->postJson($url, ['reason' => 'Spam', 'details' => 'Off-platform payment'])->assertCreated();
        $this->postJson($url, ['reason' => 'Abuse'])->assertOk();
        $this->postJson("/api/v1/conversations/{$conversation->id}/messages/{$ownMessage->id}/report", ['reason' => 'Spam'])->assertNotFound();
        $this->postJson($url, ['reason' => 'Made up'])->assertUnprocessable();

        $this->assertDatabaseCount('message_reports', 1);
        $this->assertDatabaseHas('message_reports', ['conversation_message_id' => $sellerMessage->id, 'reporter_id' => $buyer->id, 'reason' => 'Spam']);
        $this->actingAs(User::factory()->create())->postJson($url, ['reason' => 'Spam'])->assertNotFound();
    }

    public function test_retired_room_id_resolves_then_checks_access(): void
    {
        [$buyer, , $conversation] = $this->conversation();
        $oldId = (string) Str::ulid();
        DB::table('conversation_aliases')->insert(['old_id' => $oldId, 'conversation_id' => $conversation->id]);

        $this->actingAs($buyer)->getJson("/api/v1/conversations/{$oldId}")->assertOk()
            ->assertJsonPath('data.id', $conversation->id);
        $this->get("/messages/{$oldId}")->assertOk();
        $this->actingAs(User::factory()->create())->getJson("/api/v1/conversations/{$oldId}")->assertNotFound();
    }

    public function test_unread_messages_are_tracked_individually_across_inquiries(): void
    {
        [$buyer, $seller, $conversation] = $this->conversation();
        $first = $conversation->messages()->create(['sender_id' => $seller->id, 'client_message_id' => (string) Str::uuid(), 'type' => 'text', 'body' => 'First answer']);
        $second = $conversation->messages()->create(['sender_id' => $seller->id, 'client_message_id' => (string) Str::uuid(), 'type' => 'text', 'body' => 'Second answer']);
        DB::table('conversation_message_reads')->insert(['message_id' => $second->id, 'user_id' => $buyer->id, 'read_at' => now()]);

        $this->actingAs($buyer)->getJson('/api/v1/conversations')->assertOk()
            ->assertJsonPath('data.0.unread_count', 1);
        $this->postJson("/api/v1/conversations/{$conversation->id}/read")->assertOk();
        $this->getJson('/api/v1/conversations')->assertOk()->assertJsonPath('data.0.unread_count', 0);
        $this->assertDatabaseHas('conversation_message_reads', ['message_id' => $first->id, 'user_id' => $buyer->id]);
    }

    /**
     * @return array{0: User, 1: User, 2: Conversation, 3: Store, 4: Product}
     */
    private function conversation(): array
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();
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
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $seller->id, 'role' => 'owner']);

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

        return [$buyer, $seller, $conversation, $store, $product];
    }
}
