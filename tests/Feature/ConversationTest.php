<?php

namespace Tests\Feature;

use App\Livewire\Chat\Thread;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_buyer_can_start_only_one_product_conversation(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $product = $this->product();

        $this->actingAs($buyer)->post(route('messages.products.start', $product))->assertRedirect();
        $this->actingAs($buyer)->post(route('messages.products.start', $product))->assertRedirect();

        $this->assertSame(1, Conversation::count());
        $this->assertDatabaseHas('conversations', ['buyer_id' => $buyer->id, 'product_id' => $product->id, 'store_id' => $product->store_id]);
    }

    public function test_buyer_cannot_view_another_buyers_conversation(): void
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $otherBuyer = User::factory()->create(['email_verified_at' => now()]);
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);

        $this->actingAs($otherBuyer)->get(route('messages.show', $conversation))->assertForbidden();
    }

    public function test_admin_cannot_access_chat_inbox(): void
    {
        $admin = User::factory()->administrator()->create(['email_verified_at' => now()]);

        $this->actingAs($admin)->get(route('messages.index'))->assertForbidden();
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

    public function test_chat_history_limit_cannot_exceed_two_hundred_messages(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();
        $conversation = Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $product->id]);

        $this->actingAs($buyer);

        Livewire::test(Thread::class, ['conversation' => $conversation])
            ->call('loadOlderMessages')
            ->call('loadOlderMessages')
            ->call('loadOlderMessages')
            ->call('loadOlderMessages')
            ->assertSet('messageLimit', 200);
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

    public function test_conversation_rejects_invalid_or_mismatched_contexts(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();
        $otherProduct = $this->product();

        $this->expectException(DomainException::class);
        Conversation::create(['buyer_id' => $buyer->id, 'store_id' => $product->store_id, 'product_id' => $otherProduct->id]);
    }

    private function product(): Product
    {
        $suffix = str()->lower(str()->random(8));
        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => "Store {$suffix}", 'slug' => "store-{$suffix}", 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);

        return Product::create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'name' => "Product {$suffix}", 'slug' => "product-{$suffix}", 'sku' => "SKU-{$suffix}", 'price' => 199, 'stock_quantity' => 3, 'status' => 'active']);
    }
}
