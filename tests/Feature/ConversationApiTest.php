<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            ->assertCreated()
            ->assertJsonPath('data.product.id', $fresh->id);

        $this->actingAs($buyer)
            ->postJson('/api/v1/conversations/start', ['product_id' => $fresh->id])
            ->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertDatabaseCount('conversations', 2);
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
