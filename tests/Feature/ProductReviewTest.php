<?php

namespace Tests\Feature;

use App\Livewire\Reviews\Thread;
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
use Livewire\Livewire;
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

    public function test_seller_can_review_a_purchase_from_another_store_as_a_customer(): void
    {
        [$buyer, $item] = $this->deliveredOrderItem();
        $buyer->update(['account_type' => 'seller']);
        $otherTenant = Tenant::create(['name' => 'Own store', 'slug' => 'own-store', 'status' => 'active']);
        TenantMember::create(['tenant_id' => $otherTenant->id, 'user_id' => $buyer->id, 'role' => 'owner']);

        $this->actingAs($buyer)->post(route('reviews.store', $item), ['rating' => 5, 'body' => 'Great product.'])
            ->assertRedirect(route('orders.show', $item->sellerOrder->order_id));

        $this->assertDatabaseHas('product_reviews', ['order_item_id' => $item->id, 'user_id' => $buyer->id, 'status' => 'published']);
    }

    public function test_seller_cannot_review_a_purchase_from_their_own_store(): void
    {
        [$buyer, $item] = $this->deliveredOrderItem();
        $buyer->update(['account_type' => 'seller']);
        TenantMember::create(['tenant_id' => $item->sellerOrder->tenant_id, 'user_id' => $buyer->id, 'role' => 'manager']);

        $this->actingAs($buyer)->post(route('reviews.store', $item), ['rating' => 5])->assertForbidden();

        $this->assertDatabaseMissing('product_reviews', ['order_item_id' => $item->id]);
    }

    public function test_seller_my_reviews_shows_only_their_customer_reviews(): void
    {
        [$seller, $item] = $this->deliveredOrderItem();
        $seller->update(['account_type' => 'seller']);
        [$otherBuyer, $otherItem] = $this->deliveredOrderItem();
        ProductReview::create(['product_id' => $item->product_id, 'order_item_id' => $item->id, 'user_id' => $seller->id, 'rating' => 5, 'body' => 'My purchase']);
        ProductReview::create(['product_id' => $otherItem->product_id, 'order_item_id' => $otherItem->id, 'user_id' => $otherBuyer->id, 'rating' => 1, 'body' => 'Someone else']);

        $this->actingAs($seller)->get(route('reviews.index'))
            ->assertOk()->assertSee('My purchase')->assertDontSee('Someone else');
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
            ->assertSee('1 review')
            ->assertDontSee('Product questions');
    }

    public function test_only_the_products_seller_can_respond_to_a_review(): void
    {
        [$buyer, $item, $seller] = $this->deliveredOrderItem();
        $review = ProductReview::create(['product_id' => $item->product_id, 'order_item_id' => $item->id, 'user_id' => $buyer->id, 'rating' => 5, 'body' => 'Great delivery.']);
        $otherSeller = User::factory()->seller()->create();

        $this->actingAs($otherSeller)->post(route('seller.reviews.respond', $review), ['body' => 'Thank you'])->assertForbidden();
        $this->actingAs($seller)->post(route('seller.reviews.respond', $review), ['body' => 'Thank you'])->assertRedirect();
        $this->assertDatabaseHas('product_review_responses', ['product_review_id' => $review->id, 'user_id' => $seller->id, 'body' => 'Thank you']);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $seller->id, 'action' => 'product_review.response_saved', 'entity_id' => $review->id]);
        $this->actingAs($seller)->get(route('seller.reviews.index'))
            ->assertOk()
            ->assertSeeInOrder(['Great delivery.', 'Thank you']);
    }

    public function test_seller_reviews_page_renders_multiple_threads_without_lazy_loading(): void
    {
        [$buyer, $item, $seller] = $this->deliveredOrderItem();
        $review = ProductReview::create(['product_id' => $item->product_id, 'order_item_id' => $item->id, 'user_id' => $buyer->id, 'rating' => 5, 'body' => 'First review.']);
        $review->responses()->create(['user_id' => $seller->id, 'author_type' => 'seller', 'body' => 'Store response.']);
        $secondItem = $item->sellerOrder->items()->create(['product_id' => $item->product_id, 'product_name' => $item->product_name, 'sku' => $item->sku, 'unit_price' => 100, 'quantity' => 1, 'line_total' => 100]);
        ProductReview::create(['product_id' => $item->product_id, 'order_item_id' => $secondItem->id, 'user_id' => $buyer->id, 'rating' => 4, 'body' => 'Second review.']);

        $this->actingAs($seller)->get(route('seller.reviews.index'))
            ->assertOk()
            ->assertSee('Store response.')
            ->assertSee('Second review.');
    }

    public function test_review_report_control_is_hidden_while_moderation_endpoint_remains_available(): void
    {
        [$buyer, $item] = $this->deliveredOrderItem();
        $review = ProductReview::create(['product_id' => $item->product_id, 'order_item_id' => $item->id, 'user_id' => $buyer->id, 'rating' => 4, 'status' => 'published']);
        $otherBuyer = User::factory()->create();

        $this->actingAs($otherBuyer)->get(route('catalog.show', [$item->product->store->slug, $item->product->slug]))
            ->assertOk()
            ->assertDontSee('Report reason')
            ->assertDontSee(route('reviews.report', $review));

        $this->post(route('reviews.report', $review), ['reason' => 'Inappropriate content'])->assertRedirect();
        $this->assertDatabaseHas('product_review_reports', ['product_review_id' => $review->id, 'user_id' => $otherBuyer->id]);
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

    public function test_buyer_and_seller_can_continue_a_review_thread_under_the_store_identity(): void
    {
        [$buyer, $item, $seller] = $this->deliveredOrderItem();
        $review = ProductReview::create(['product_id' => $item->product_id, 'order_item_id' => $item->id, 'user_id' => $buyer->id, 'rating' => 5, 'body' => 'Great delivery.', 'status' => 'published']);
        $store = $item->product->store;
        $store->update(['name' => 'Official Tote Shop', 'logo_path' => 'stores/tote-logo.png']);

        $this->actingAs($seller);
        Livewire::test(Thread::class, ['review' => $review, 'sellerView' => true])
            ->set('body', 'Thank you for your feedback.')
            ->call('submit')
            ->assertSee('Official Tote Shop')
            ->assertSee('Thank you for your feedback.');
        $root = $review->response()->firstOrFail();
        $this->assertSame('seller', $root->author_type);

        $this->actingAs($buyer);
        Livewire::test(Thread::class, ['review' => $review])
            ->assertSeeInOrder(['Thank you for your feedback.', 'Write a reply...'])
            ->set('replyBodies.'.$root->id, 'Can you help with the size?')
            ->call('submitCustomerReply', $root->id)
            ->assertSee('Can you help with the size?');
        $buyerReply = $review->responses()->where('author_type', 'buyer')->firstOrFail();
        $this->assertSame($root->id, $buyerReply->parent_response_id);

        $this->actingAs($seller);
        Livewire::test(Thread::class, ['review' => $review, 'sellerView' => true])
            ->call('replyTo', $buyerReply->id)
            ->set('body', 'Yes, we can help.')
            ->call('submit')
            ->assertSet('body', 'Thank you for your feedback.')
            ->assertSeeInOrder(['Thank you for your feedback.', 'Can you help with the size?', 'Yes, we can help.'])
            ->assertDontSee($seller->name);

        $this->assertDatabaseHas('product_review_responses', ['product_review_id' => $review->id, 'parent_response_id' => $buyerReply->id, 'author_type' => 'seller', 'body' => 'Yes, we can help.']);
        $followup = $review->responses()->where('parent_response_id', $buyerReply->id)->firstOrFail();
        $this->actingAs($buyer);
        $customerThread = Livewire::test(Thread::class, ['review' => $review]);
        $this->assertSame(2, substr_count($customerThread->html(), 'placeholder="Write a reply..."'));
        $customerThread->set('replyBodies.'.$root->id, 'Draft for the first response')
            ->set('replyBodies.'.$followup->id, 'That answers my question.')
            ->call('submitCustomerReply', $followup->id)
            ->assertSet('replyBodies.'.$root->id, 'Draft for the first response')
            ->assertSet('replyBodies.'.$followup->id, '')
            ->assertSee('Reply sent.')
            ->assertSee('That answers my question.');
        $this->assertDatabaseHas('product_review_responses', ['product_review_id' => $review->id, 'parent_response_id' => $followup->id, 'author_type' => 'buyer', 'body' => 'That answers my question.']);
        $this->actingAs($seller);
        Livewire::test(Thread::class, ['review' => $review, 'sellerView' => true])
            ->set('body', 'Thanks again for your feedback.')
            ->call('submit')
            ->assertSee('Can you help with the size?');
        $this->assertSame(4, $review->responses()->count());
        $this->assertSame('Thanks again for your feedback.', $root->fresh()->body);
        $this->get(route('catalog.show', [$store->slug, $item->product->slug]))
            ->assertOk()
            ->assertSee('Official Tote Shop')
            ->assertSee('stores/tote-logo.png');
    }

    public function test_thread_rejects_impersonation_cross_review_parent_and_hidden_reviews(): void
    {
        [$buyer, $item, $seller] = $this->deliveredOrderItem();
        $review = ProductReview::create(['product_id' => $item->product_id, 'order_item_id' => $item->id, 'user_id' => $buyer->id, 'rating' => 5]);
        $root = $review->responses()->create(['user_id' => $seller->id, 'author_type' => 'seller', 'body' => 'Thanks']);
        [$otherBuyer, $otherItem, $otherSeller] = $this->deliveredOrderItem();
        $otherReview = ProductReview::create(['product_id' => $otherItem->product_id, 'order_item_id' => $otherItem->id, 'user_id' => $otherBuyer->id, 'rating' => 4]);
        $otherRoot = $otherReview->responses()->create(['user_id' => $otherSeller->id, 'author_type' => 'seller', 'body' => 'Other store']);

        $this->actingAs($otherBuyer);
        Livewire::test(Thread::class, ['review' => $review])->set('replyBodies.'.$root->id, 'Impersonation')->call('submitCustomerReply', $root->id)->assertForbidden();
        $this->actingAs($otherSeller);
        Livewire::test(Thread::class, ['review' => $review, 'sellerView' => true])->set('replyBodies.'.$root->id, 'Other store')->call('submitCustomerReply', $root->id)->assertForbidden();
        $this->actingAs($buyer);
        Livewire::test(Thread::class, ['review' => $review])->set('replyBodies.'.$otherRoot->id, 'Wrong thread')->call('submitCustomerReply', $otherRoot->id)->assertNotFound();
        Livewire::test(Thread::class, ['review' => $review])->set('replyBodies.'.$root->id, ' ')->call('submitCustomerReply', $root->id)->assertHasErrors('replyBodies.'.$root->id);
        $this->actingAs(User::factory()->unverified()->create());
        Livewire::test(Thread::class, ['review' => $review])->set('replyBodies.'.$root->id, 'Unverified')->call('submitCustomerReply', $root->id)->assertForbidden();
        $review->update(['status' => 'hidden']);
        $this->actingAs($buyer);
        Livewire::test(Thread::class, ['review' => $review])->set('replyBodies.'.$root->id, 'Hidden')->call('submitCustomerReply', $root->id)->assertForbidden();
        $this->actingAs($seller)->post(route('seller.reviews.respond', $review), ['body' => 'Private response'])->assertRedirect();
        $this->assertSame('Private response', $root->fresh()->body);
        $this->assertSame(1, $review->responses()->count());
    }

    public function test_guest_cannot_post_a_review_reply(): void
    {
        [$buyer, $item, $seller] = $this->deliveredOrderItem();
        $review = ProductReview::create(['product_id' => $item->product_id, 'order_item_id' => $item->id, 'user_id' => $buyer->id, 'rating' => 5]);
        $root = $review->responses()->create(['user_id' => $seller->id, 'author_type' => 'seller', 'body' => 'Thanks']);

        Livewire::test(Thread::class, ['review' => $review])->set('replyBodies.'.$root->id, 'Guest')->call('submitCustomerReply', $root->id)->assertForbidden();
        $this->assertSame(1, $review->responses()->count());
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
