<?php

namespace Tests\Feature;

use App\Actions\MarkConversationRead;
use App\Livewire\MessageHeaderBadge;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class HeaderWorkspaceScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_workspace_shows_only_the_buyer_side_unread_count_and_the_cart_and_bell(): void
    {
        [$seller] = $this->sellerWhoAlsoBuys();

        $this->actingAs($seller)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSee('aria-label="Messages, 1 unread"', false)
            ->assertSee('data-cart-link', false)
            ->assertSee('data-notification-bell', false);
    }

    public function test_seller_workspace_shows_only_the_seller_side_unread_count_and_hides_the_cart_but_keeps_the_bell(): void
    {
        [$seller] = $this->sellerWhoAlsoBuys();

        $this->actingAs($seller)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Messages, 1 unread"', false)
            ->assertDontSee('data-cart-link', false)
            ->assertSee('data-notification-bell', false);
    }

    public function test_header_message_counter_refreshes_after_messages_are_read(): void
    {
        [$seller, $buyerConversation] = $this->sellerWhoAlsoBuys();

        $buyerBadge = Livewire::actingAs($seller)->test(MessageHeaderBadge::class, ['sellerWorkspace' => false])
            ->assertViewHas('unreadCount', 1)
            ->assertSee('Messages, 1 unread');

        app(MarkConversationRead::class)->handle($buyerConversation, $seller);

        $buyerBadge->call('$refresh')
            ->assertViewHas('unreadCount', 0)
            ->assertDontSee('Messages, 1 unread');

        $buyerConversation->messages()->create([
            'sender_id' => $buyerConversation->messages()->firstOrFail()->sender_id,
            'client_message_id' => (string) Str::uuid(),
            'type' => 'text',
            'body' => 'New update',
        ]);

        $buyerBadge->call('$refresh')->assertViewHas('unreadCount', 1);

        Livewire::actingAs($seller)->test(MessageHeaderBadge::class, ['sellerWorkspace' => true])
            ->assertViewHas('unreadCount', 1);
    }

    /** @return array{0: User, 1: Conversation} the dual-role user and their buyer-side unread conversation */
    private function sellerWhoAlsoBuys(): array
    {
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);

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
            'name' => 'Owned Store Tote',
            'slug' => "owned-store-tote-{$suffix}",
            'sku' => "OWNED-TOTE-{$suffix}",
            'price' => '199.00',
            'stock_quantity' => 5,
            'status' => 'active',
        ]);

        // A shopper messages the seller's own store: unread for the seller, as seller.
        $shopper = User::factory()->create();
        $sellerSideConversation = Conversation::create([
            'buyer_id' => $shopper->id,
            'store_id' => $store->id,
            'product_id' => $product->id,
        ]);
        $sellerSideConversation->messages()->create([
            'sender_id' => $shopper->id,
            'client_message_id' => (string) Str::uuid(),
            'type' => 'text',
            'body' => 'Still in stock?',
        ]);

        // The seller shops at someone else's store: unread for the seller, as buyer.
        $otherTenant = Tenant::create(['name' => "Other {$suffix}", 'slug' => "other-{$suffix}", 'status' => 'active']);
        $otherStore = Store::create([
            'tenant_id' => $otherTenant->id,
            'name' => "Other Store {$suffix}",
            'slug' => "other-store-{$suffix}",
            'phone' => '+639171234567',
            'address_line' => '20 Market Street',
            'city' => 'Manila',
            'status' => 'active',
        ]);
        $otherOwner = User::factory()->seller()->create();
        TenantMember::create(['tenant_id' => $otherTenant->id, 'user_id' => $otherOwner->id, 'role' => 'owner']);
        $otherProduct = Product::create([
            'tenant_id' => $otherTenant->id,
            'store_id' => $otherStore->id,
            'name' => 'Other Store Tote',
            'slug' => "other-store-tote-{$suffix}",
            'sku' => "OTHER-TOTE-{$suffix}",
            'price' => '199.00',
            'stock_quantity' => 5,
            'status' => 'active',
        ]);
        $buyerSideConversation = Conversation::create([
            'buyer_id' => $seller->id,
            'store_id' => $otherStore->id,
            'product_id' => $otherProduct->id,
        ]);
        $buyerSideConversation->messages()->create([
            'sender_id' => $otherOwner->id,
            'client_message_id' => (string) Str::uuid(),
            'type' => 'text',
            'body' => 'Yes, 5 left.',
        ]);

        return [$seller, $buyerSideConversation];
    }
}
