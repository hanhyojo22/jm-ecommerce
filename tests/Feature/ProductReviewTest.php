<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivered_buyer_can_review_their_own_order_item_once(): void
    {
        [$buyer, $item] = $this->deliveredOrderItem();

        $this->actingAs($buyer)->post(route('reviews.store', $item), ['rating' => 5, 'title' => 'Excellent', 'body' => 'Exactly as described.'])
            ->assertRedirect(route('orders.show', $item->sellerOrder->order_id));

        $this->assertDatabaseHas('product_reviews', ['order_item_id' => $item->id, 'user_id' => $buyer->id, 'rating' => 5, 'status' => 'published']);
        $this->actingAs($buyer)->post(route('reviews.store', $item), ['rating' => 4])->assertForbidden();
    }

    public function test_buyer_cannot_review_another_buyers_delivered_order_item(): void
    {
        [, $item] = $this->deliveredOrderItem();
        $otherBuyer = User::factory()->create();

        $this->actingAs($otherBuyer)->post(route('reviews.store', $item), ['rating' => 5])->assertForbidden();

        $this->assertDatabaseMissing('product_reviews', ['order_item_id' => $item->id]);
    }

    public function test_undelivered_order_item_cannot_be_reviewed(): void
    {
        [$buyer, $item] = $this->deliveredOrderItem('shipped');

        $this->actingAs($buyer)->post(route('reviews.store', $item), ['rating' => 5])->assertForbidden();
    }

    public function test_hidden_reviews_are_excluded_from_public_rating_calculations(): void
    {
        [$buyer, $item] = $this->deliveredOrderItem();
        $review = ProductReview::create(['product_id' => $item->product_id, 'order_item_id' => $item->id, 'user_id' => $buyer->id, 'rating' => 5, 'status' => 'published']);
        [, $hiddenItem] = $this->deliveredOrderItem();
        ProductReview::create(['product_id' => $item->product_id, 'order_item_id' => $hiddenItem->id, 'user_id' => $hiddenItem->sellerOrder->order->user_id, 'rating' => 1, 'status' => 'hidden']);
        $product = $review->product;

        $this->get(route('catalog.show', [$product->store->slug, $product->slug]))
            ->assertSee('5.0')
            ->assertSee('1 review');
    }

    public function test_only_the_products_seller_can_respond_to_a_review(): void
    {
        [$buyer, $item, $seller] = $this->deliveredOrderItem();
        $review = ProductReview::create(['product_id' => $item->product_id, 'order_item_id' => $item->id, 'user_id' => $buyer->id, 'rating' => 5]);
        $otherSeller = User::factory()->seller()->create();

        $this->actingAs($otherSeller)->post(route('seller.reviews.respond', $review), ['body' => 'Thank you'])->assertForbidden();
        $this->actingAs($seller)->post(route('seller.reviews.respond', $review), ['body' => 'Thank you'])->assertRedirect();
        $this->assertDatabaseHas('product_review_responses', ['product_review_id' => $review->id, 'user_id' => $seller->id, 'body' => 'Thank you']);
    }

    public function test_admin_moderation_records_an_audit_trail(): void
    {
        [$buyer, $item] = $this->deliveredOrderItem();
        $review = ProductReview::create(['product_id' => $item->product_id, 'order_item_id' => $item->id, 'user_id' => $buyer->id, 'rating' => 2]);
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->patch(route('admin.reviews.moderate', $review), ['status' => 'hidden', 'reason' => 'Contains personal information.'])->assertRedirect();

        $this->assertDatabaseHas('product_reviews', ['id' => $review->id, 'status' => 'hidden', 'moderated_by' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $admin->id, 'action' => 'product_review.moderated', 'entity_id' => $review->id]);
    }

    /** @return array{0: User, 1: OrderItem, 2: User} */
    private function deliveredOrderItem(string $status = 'delivered'): array
    {
        $seller = User::factory()->seller()->create();
        $buyer = User::factory()->create();
        $suffix = str()->lower(str()->random(8));
        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => "Store {$suffix}", 'slug' => "store-{$suffix}", 'phone' => '+639171234567', 'address_line' => '1 Market Street', 'city' => 'Manila', 'status' => 'active']);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $seller->id, 'role' => 'owner']);
        $product = Product::create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'name' => 'Reviewable bag', 'slug' => "reviewable-bag-{$suffix}", 'sku' => "SKU-{$suffix}", 'price' => 100, 'stock_quantity' => 1, 'status' => 'active']);
        $order = Order::create(['user_id' => $buyer->id, 'number' => "ORD-{$suffix}", 'status' => $status, 'recipient_name' => 'Buyer', 'phone' => '+639171234567', 'address_line' => '1 Market Street', 'city' => 'Manila', 'subtotal' => 100, 'shipping_amount' => 0, 'total' => 100]);
        $sellerOrder = $order->sellerOrders()->create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'status' => $status, 'subtotal' => 100, 'shipping_amount' => 0, 'total' => 100]);
        $item = $sellerOrder->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku, 'unit_price' => 100, 'quantity' => 1, 'line_total' => 100]);

        return [$buyer, $item, $seller];
    }
}
