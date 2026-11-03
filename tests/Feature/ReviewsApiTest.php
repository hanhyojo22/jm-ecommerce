<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_delivered_buyer_publishes_and_edits_a_review(): void
    {
        [$buyer, $item] = $this->deliveredItem();

        $response = $this->actingAs($buyer)
            ->postJson("/api/v1/order-items/{$item->id}/review", [
                'rating' => 5,
                'title' => 'Excellent',
                'body' => 'Exactly as described.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.body', 'Exactly as described.')
            ->assertJsonPath('data.author', $buyer->name)
            ->assertJsonPath('data.verified_purchase', true);

        $reviewId = $response->json('data.id');

        $this->actingAs($buyer)
            ->patchJson("/api/v1/reviews/{$reviewId}", ['rating' => 4, 'body' => 'Still good.'])
            ->assertOk()
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.body', 'Still good.');

        $this->actingAs($buyer)
            ->deleteJson("/api/v1/reviews/{$reviewId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('product_reviews', ['id' => $reviewId]);
    }

    public function test_a_buyer_cannot_review_an_item_they_did_not_receive_or_review_twice(): void
    {
        [$buyer, $item] = $this->deliveredItem();
        $otherBuyer = User::factory()->create();

        $this->actingAs($otherBuyer)
            ->postJson("/api/v1/order-items/{$item->id}/review", ['rating' => 5])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'resource.not_found');

        $this->actingAs($buyer)
            ->postJson("/api/v1/order-items/{$item->id}/review", ['rating' => 5])
            ->assertCreated();

        $this->actingAs($buyer)
            ->postJson("/api/v1/order-items/{$item->id}/review", ['rating' => 4])
            ->assertNotFound();

        $this->assertDatabaseCount('product_reviews', 1);
    }

    /**
     * @return array{0: User, 1: OrderItem}
     */
    private function deliveredItem(): array
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
            'name' => 'Review API Tote',
            'slug' => "review-api-tote-{$suffix}",
            'sku' => "REVIEW-TOTE-{$suffix}",
            'price' => '199.00',
            'stock_quantity' => 5,
            'status' => 'active',
        ]);
        $order = Order::create([
            'user_id' => $buyer->id,
            'number' => 'JM-'.str()->upper(str()->random(8)),
            'status' => 'delivered',
            'recipient_name' => $buyer->name,
            'phone' => '+639171234567',
            'address_line' => '100 Market Street',
            'city' => 'Manila',
            'subtotal' => '199.00',
            'shipping_amount' => '0.00',
            'total' => '199.00',
        ]);
        $order->payment()->create(['method' => 'cod', 'status' => 'paid', 'amount' => '199.00']);
        $sellerOrder = $order->sellerOrders()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'status' => 'delivered',
            'subtotal' => '199.00',
            'shipping_amount' => '0.00',
            'total' => '199.00',
            'delivered_at' => now(),
        ]);
        $item = $sellerOrder->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => '199.00',
            'quantity' => 1,
            'line_total' => '199.00',
        ]);

        return [$buyer, $item];
    }
}
