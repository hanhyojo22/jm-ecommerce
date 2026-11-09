<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDiscussionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_feed_shows_only_published_threads_and_replies(): void
    {
        $product = $this->product();
        $buyer = User::factory()->create();
        $root = $product->discussions()->create(['user_id' => $buyer->id, 'author_type' => 'buyer', 'body' => 'Does it fit?']);
        $product->discussions()->create(['user_id' => $buyer->id, 'author_type' => 'buyer', 'body' => 'Yes', 'parent_id' => $root->id, 'root_id' => $root->id]);
        $product->discussions()->create(['user_id' => $buyer->id, 'author_type' => 'buyer', 'body' => 'Hidden', 'status' => 'hidden']);

        $this->getJson(route('api.v1.products.discussions.index', [$product->store->slug, $product->slug]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'Does it fit?')
            ->assertJsonPath('data.0.replies.0.body', 'Yes');
    }

    public function test_verified_buyer_can_post_and_reply_with_scoped_parent(): void
    {
        $product = $this->product();
        $other = $this->product();
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $foreign = $other->discussions()->create(['user_id' => $buyer->id, 'author_type' => 'buyer', 'body' => 'Other product']);
        $url = route('api.v1.products.discussions.store', [$product->store->slug, $product->slug]);

        $this->actingAs($buyer)->postJson($url, ['body' => 'Wrong', 'parent_id' => $foreign->id])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation.failed')
            ->assertJsonStructure(['error' => ['fields' => ['parent_id']]]);
        $root = $this->actingAs($buyer)->postJson($url, ['body' => '  Does it fit?  '])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Does it fit?');
        $this->actingAs($buyer)->postJson($url, ['body' => 'Thanks', 'parent_id' => $root->json('data.id')])
            ->assertCreated()
            ->assertJsonPath('data.root_id', $root->json('data.id'));

        $this->assertDatabaseHas('product_discussions', ['product_id' => $product->id, 'body' => 'Does it fit?', 'author_type' => 'buyer']);
        $this->assertDatabaseCount('product_discussions', 3);
    }

    public function test_discussion_api_rejects_unverified_and_hidden_product_posts(): void
    {
        $product = $this->product();
        $url = route('api.v1.products.discussions.store', [$product->store->slug, $product->slug]);

        $this->postJson($url, ['body' => 'Guest'])->assertUnauthorized();
        $this->actingAs(User::factory()->unverified()->create())->postJson($url, ['body' => 'Unverified'])->assertForbidden();
        $product->update(['status' => 'draft']);
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
            ->postJson($url, ['body' => 'Hidden'])->assertNotFound();
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
