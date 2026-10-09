<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductReviewPhoto;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
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

    public function test_review_photo_files_are_removed_when_photo_record_creation_fails(): void
    {
        Storage::fake('public');
        [$buyer, $item] = $this->deliveredOrderItem();
        ProductReviewPhoto::creating(static function (): void {
            throw new RuntimeException('Photo record could not be created.');
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($buyer)
                ->post(route('reviews.store', $item), [
                    'rating' => 5,
                    'photos' => [UploadedFile::fake()->image('review.jpg')],
                ]);
            $this->fail('Expected the photo record failure to be rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Photo record could not be created.', $exception->getMessage());
        } finally {
            ProductReviewPhoto::flushEventListeners();
        }

        $this->assertDatabaseMissing('product_reviews', ['order_item_id' => $item->id]);
        $this->assertSame([], Storage::disk('public')->allFiles('product-reviews'));
    }

    public function test_review_update_appends_uploaded_photos_in_contiguous_order(): void
    {
        Storage::fake('public');
        [$buyer, $item] = $this->deliveredOrderItem();
        $review = ProductReview::create([
            'product_id' => $item->product_id,
            'order_item_id' => $item->id,
            'user_id' => $buyer->id,
            'rating' => 4,
        ]);
        $review->photos()->create(['path' => 'product-reviews/original.jpg', 'position' => 0]);
        Storage::disk('public')->put('product-reviews/original.jpg', 'original');

        $this->actingAs($buyer)->patch(route('reviews.update', $review), [
            'rating' => 5,
            'photos' => [
                UploadedFile::fake()->image('first.jpg'),
                UploadedFile::fake()->image('second.jpg'),
            ],
        ])->assertRedirect();

        $this->assertSame([0, 1, 2], $review->photos()->orderBy('position')->pluck('position')->all());
        $this->assertCount(3, Storage::disk('public')->allFiles('product-reviews'));
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
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $seller->id, 'action' => 'product_review.response_saved', 'entity_id' => $review->id]);
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
