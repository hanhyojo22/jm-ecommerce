<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductDiscussion;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductDiscussionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_read_public_discussions_with_escaped_content(): void
    {
        $product = $this->product();
        $buyer = User::factory()->create();
        $product->discussions()->create(['user_id' => $buyer->id, 'author_type' => 'buyer', 'body' => '<script>alert(1)</script>']);

        $this->get(route('catalog.show', [$product->store->slug, $product->slug]))
            ->assertOk()
            ->assertSee('Product discussions')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_seller_uses_store_identity_only_on_a_store_they_manage(): void
    {
        $ownProduct = $this->product();
        $otherProduct = $this->product();
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $ownProduct->tenant_id, 'user_id' => $seller->id, 'role' => 'owner']);
        $ownProduct->store->update(['logo_path' => 'logos/seller.png']);

        $this->actingAs($seller)->post(route('discussions.store', $ownProduct), ['body' => 'Official answer'])->assertRedirect();
        $this->actingAs($seller)->post(route('discussions.store', $otherProduct), ['body' => 'Customer question'])->assertRedirect();

        $this->assertDatabaseHas('product_discussions', ['product_id' => $ownProduct->id, 'user_id' => $seller->id, 'author_type' => 'seller']);
        $this->assertDatabaseHas('product_discussions', ['product_id' => $otherProduct->id, 'user_id' => $seller->id, 'author_type' => 'buyer']);
        $this->actingAs($seller)->get(route('catalog.show', [$ownProduct->store->slug, $ownProduct->slug]))
            ->assertSee($ownProduct->store->name)->assertSee('Seller')
            ->assertSee('src="'.Storage::disk('public')->url('logos/seller.png').'"', false);
        $this->actingAs($seller)->get(route('catalog.show', [$otherProduct->store->slug, $otherProduct->slug]))
            ->assertSee($seller->name)->assertSee('Customer question');
    }

    public function test_seller_conversation_reply_returns_rendered_message_without_reloading_reviews(): void
    {
        $product = $this->product();
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $product->tenant_id, 'user_id' => $seller->id, 'role' => 'owner']);
        $question = $product->discussions()->create(['user_id' => $buyer->id, 'author_type' => 'buyer', 'body' => 'Is it blue?']);

        $response = $this->actingAs($seller)->postJson(route('discussions.store', $product), [
            'body' => '<Blue & ready>',
            'parent_id' => $question->id,
            'seller_reviews' => true,
            'discussion_page' => 1,
        ])->assertOk();

        $this->assertStringContainsString('&lt;Blue &amp; ready&gt;', $response->json('html'));
        $this->assertStringNotContainsString('<Blue & ready>', $response->json('html'));
        $this->assertDatabaseHas('product_discussions', ['parent_id' => $question->id, 'body' => '<Blue & ready>', 'author_type' => 'seller']);
    }

    public function test_product_page_forms_return_rendered_fragments_for_optimistic_updates(): void
    {
        $product = $this->product();
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $product->tenant_id, 'user_id' => $seller->id, 'role' => 'owner']);

        $root = $this->actingAs($buyer)->postJson(route('discussions.store', $product), [
            'body' => 'Will this fit a 15 inch laptop?',
        ])->assertOk();

        $this->assertNull($root->json('parent_id'));
        $this->assertStringContainsString('id="discussion-'.$root->json('id').'"', $root->json('html'));
        $this->assertStringContainsString('data-discussion-root-id="'.$root->json('id').'"', $root->json('html'));
        $this->assertStringContainsString('data-discussion-form', $root->json('html'));
        $this->assertStringContainsString('Will this fit a 15 inch laptop?', $root->json('html'));

        $reply = $this->actingAs($seller)->postJson(route('discussions.store', $product), [
            'body' => '<Blue & ready>',
            'parent_id' => $root->json('id'),
        ])->assertOk();

        $this->assertSame($root->json('id'), $reply->json('parent_id'));
        $this->assertStringContainsString('id="discussion-'.$reply->json('id').'"', $reply->json('html'));
        $this->assertStringContainsString('data-discussion-root-id="'.$root->json('id').'"', $reply->json('html'));
        $this->assertStringContainsString('&lt;Blue &amp; ready&gt;', $reply->json('html'));
        $this->assertStringContainsString('data-author-is-seller="1"', $reply->json('html'));
    }

    public function test_customers_can_reply_to_each_other_and_to_seller_answers(): void
    {
        $product = $this->product();
        $firstCustomer = User::factory()->create(['email_verified_at' => now()]);
        $secondCustomer = User::factory()->create(['email_verified_at' => now()]);
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $product->tenant_id, 'user_id' => $seller->id, 'role' => 'manager']);

        $this->actingAs($firstCustomer)->post(route('discussions.store', $product), ['body' => 'Will this fit?'])->assertRedirect();
        $root = ProductDiscussion::query()->sole();
        $this->actingAs($secondCustomer)->post(route('discussions.store', $product), ['body' => 'It fit mine.', 'parent_id' => $root->id])->assertRedirect();
        $this->actingAs($seller)->post(route('discussions.store', $product), ['body' => 'Check the measurements.', 'parent_id' => $root->id])->assertRedirect();
        $sellerReply = ProductDiscussion::query()->where('author_type', 'seller')->sole();
        $this->actingAs($firstCustomer)->post(route('discussions.store', $product), ['body' => 'Thank you!', 'parent_id' => $sellerReply->id])->assertRedirect();

        $this->assertDatabaseHas('product_discussions', ['body' => 'Thank you!', 'parent_id' => $sellerReply->id, 'root_id' => $root->id, 'author_type' => 'buyer']);
    }

    public function test_cross_product_and_hidden_replies_are_rejected(): void
    {
        $product = $this->product();
        $otherProduct = $this->product();
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $parent = $otherProduct->discussions()->create(['user_id' => $customer->id, 'author_type' => 'buyer', 'body' => 'Other product']);

        $this->actingAs($customer)->post(route('discussions.store', $product), ['body' => 'Wrong thread', 'parent_id' => $parent->id])
            ->assertSessionHasErrors('parent_id');
        $parent->update(['status' => 'hidden']);
        $this->actingAs($customer)->post(route('discussions.store', $otherProduct), ['body' => 'Hidden thread', 'parent_id' => $parent->id])
            ->assertSessionHasErrors('parent_id');

        $this->assertDatabaseCount('product_discussions', 1);
    }

    public function test_only_verified_shoppers_can_post_discussions(): void
    {
        $product = $this->product();

        $this->post(route('discussions.store', $product), ['body' => 'Guest'])->assertRedirect();
        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('discussions.store', $product), ['body' => 'Admin'])->assertForbidden();
        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('discussions.store', $product), ['body' => 'Unverified'])->assertRedirect();

        $this->assertDatabaseCount('product_discussions', 0);
    }

    private function product(): Product
    {
        $suffix = str()->lower(str()->random(8));
        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => "Store {$suffix}", 'slug' => "store-{$suffix}", 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);

        return Product::create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'name' => "Product {$suffix}", 'slug' => "product-{$suffix}", 'sku' => "SKU-{$suffix}", 'price' => '199.00', 'stock_quantity' => 3, 'status' => 'active']);
    }
}
