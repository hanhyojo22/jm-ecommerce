<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PrototypeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_uploads_variant_prices_and_photos_then_submits_for_review(): void
    {
        Storage::fake('product_images');
        [$seller, $product] = $this->listing();
        $this->actingAs($seller)->post(route('seller.products.store'), [
            'name' => 'Variant camera', 'sku' => 'CAMERA', 'description' => 'A compact camera.', 'category_id' => $product->category_id, 'price' => '100.00',
            'variants' => [
                ['color' => 'Black', 'size' => 'Standard', 'sku' => 'CAMERA-B', 'price' => '120.35', 'stock_quantity' => 2, 'images' => [UploadedFile::fake()->image('black.jpg')]],
                ['color' => 'White', 'size' => 'Standard', 'sku' => 'CAMERA-W', 'price' => '130.50', 'stock_quantity' => 0, 'images' => [UploadedFile::fake()->image('white.jpg')]],
            ],
        ])->assertRedirect(route('seller.products.index'));
        $listing = Product::query()->where('sku', 'CAMERA')->with(['variants', 'images'])->sole();
        $this->assertSame('draft', $listing->status);
        $this->assertSame(2, $listing->stock_quantity);
        $this->assertSame('120.35', $listing->variants[0]->price);
        $this->assertCount(1, $listing->colorPhotos('Black'));
        Storage::disk('product_images')->assertExists($listing->colorPhotos('Black')[0]->path);

        $this->post(route('seller.products.submit', $listing))->assertRedirect(route('seller.products.index'));
        $this->assertDatabaseHas('products', ['id' => $listing->id, 'status' => 'pending']);
        $this->get(route('catalog.show', [$listing->store->slug, $listing->slug]))->assertNotFound();
        $this->get(route('seller.products.edit', $listing))->assertSee('Editing is paused');
        $this->patch(route('seller.products.update', $listing), ['name' => 'Bypassed review', 'sku' => $listing->sku, 'price' => '1.00', 'stock_quantity' => 4, 'status' => 'draft'])->assertSessionHasErrors('status');
        $this->assertDatabaseHas('products', ['id' => $listing->id, 'name' => 'Variant camera', 'status' => 'pending']);
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $listing->id, 'action' => 'product.submitted']);
    }

    public function test_variant_price_precision_and_image_uploads_are_validated_before_saving(): void
    {
        [$seller] = $this->listing();
        $this->actingAs($seller)->post(route('seller.products.store'), [
            'name' => 'Invalid variant', 'sku' => 'INVALID-VARIANT', 'price' => '10.00',
            'variants' => [['color' => 'Blue', 'size' => 'M', 'sku' => 'INVALID-BLUE', 'stock_quantity' => 1, 'price' => '12.345', 'images' => [UploadedFile::fake()->create('payload.jpg', 5, 'text/plain')]]],
        ])->assertSessionHasErrors(['variants.0.price', 'variants.0.images.0']);

        $this->assertDatabaseMissing('products', ['sku' => 'INVALID-VARIANT']);
    }

    public function test_variant_photos_are_shared_by_every_size_of_their_color(): void
    {
        Storage::fake('product_images');
        [$seller, $product] = $this->listing();

        $this->actingAs($seller)->post(route('seller.products.store'), [
            'name' => 'Shared shirt', 'sku' => 'SHIRT', 'description' => 'A cotton shirt.', 'category_id' => $product->category_id, 'price' => '100.00',
            'variants' => [
                ['color' => 'White', 'size' => 'S', 'sku' => 'SHIRT-WS', 'stock_quantity' => 2, 'images' => [UploadedFile::fake()->image('white.jpg')]],
                ['color' => 'White', 'size' => 'M', 'sku' => 'SHIRT-WM', 'stock_quantity' => 2],
                ['color' => 'Black', 'size' => 'S', 'sku' => 'SHIRT-BS', 'stock_quantity' => 2, 'images' => [UploadedFile::fake()->image('black.jpg')]],
            ],
        ])->assertRedirect(route('seller.products.index'));
        $listing = Product::query()->where('sku', 'SHIRT')->with('images')->sole();
        $this->post(route('seller.products.submit', $listing))->assertRedirect(route('seller.products.index'));

        $this->assertCount(1, $listing->colorPhotos('White'));
        $this->assertCount(1, $listing->colorPhotos('Black'));
        $this->assertDatabaseMissing('product_images', ['product_id' => $listing->id, 'color' => null]);
        $this->assertDatabaseHas('products', ['id' => $listing->id, 'status' => 'pending']);
    }

    public function test_listing_without_shared_photos_needs_photos_for_every_color(): void
    {
        Storage::fake('product_images');
        [$seller, $product] = $this->listing();
        $this->actingAs($seller)->post(route('seller.products.store'), [
            'name' => 'Half shirt', 'sku' => 'HALF', 'description' => 'A cotton shirt.', 'category_id' => $product->category_id, 'price' => '100.00',
            'variants' => [
                ['color' => 'White', 'size' => 'S', 'sku' => 'HALF-WS', 'stock_quantity' => 2, 'images' => [UploadedFile::fake()->image('white.jpg')]],
                ['color' => 'Black', 'size' => 'S', 'sku' => 'HALF-BS', 'stock_quantity' => 2],
            ],
        ]);
        $listing = Product::query()->where('sku', 'HALF')->sole();

        $this->post(route('seller.products.submit', $listing))
            ->assertSessionHasErrors(['status' => 'Add photos for every color or upload a shared product photo before submitting.']);

        $this->assertDatabaseHas('products', ['id' => $listing->id, 'status' => 'draft']);
    }

    public function test_variant_photos_are_limited_to_four_per_color_and_need_a_color(): void
    {
        Storage::fake('product_images');
        [$seller] = $this->listing();
        $photos = fn (int $count): array => array_map(fn (int $index) => UploadedFile::fake()->image("photo-$index.jpg"), range(1, $count));

        $this->actingAs($seller)->post(route('seller.products.store'), [
            'name' => 'Crowded shirt', 'sku' => 'CROWDED', 'price' => '10.00',
            'variants' => [
                ['color' => 'White', 'size' => 'S', 'sku' => 'CROWDED-WS', 'stock_quantity' => 1, 'images' => $photos(3)],
                ['color' => 'White', 'size' => 'M', 'sku' => 'CROWDED-WM', 'stock_quantity' => 1, 'images' => $photos(2)],
                ['size' => 'L', 'sku' => 'CROWDED-L', 'stock_quantity' => 1, 'images' => $photos(1)],
            ],
        ])->assertSessionHasErrors([
            'variants.1.images' => 'Each color can have no more than 4 photos. Remove a White photo to add another.',
            'variants.2.images' => 'Only a variant with a color can have its own photos. Use the product photos instead.',
        ]);

        $this->assertDatabaseMissing('products', ['sku' => 'CROWDED']);
    }

    #[TestWith(['changes_required'])]
    #[TestWith(['rejected'])]
    public function test_returned_listing_can_be_corrected_resubmitted_and_published_with_audit_history(string $decision): void
    {
        [$seller, $product] = $this->listing('pending');
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->patch(route('admin.products.moderate', $product), ['status' => $decision, 'reason' => 'Please describe the included accessories.'])->assertRedirect();
        $this->actingAs($seller)->get(route('seller.products.edit', $product))->assertSee('Please describe the included accessories.');
        $this->patch(route('seller.products.update', $product), ['name' => $product->name, 'sku' => $product->sku, 'price' => '10.00', 'stock_quantity' => 4, 'status' => 'draft', 'description' => 'Includes the carrying case.', 'category_id' => $product->category_id])->assertRedirect();
        $this->post(route('seller.products.submit', $product))->assertRedirect();
        $this->actingAs($admin)->patch(route('admin.products.moderate', $product), ['status' => 'active', 'reason' => 'Listing passed the product review.'])->assertRedirect();

        $this->get(route('catalog.show', [$product->store->slug, $product->slug]))->assertSee('Includes the carrying case.');
        $this->get(route('admin.products.show', $product))->assertSee('Please describe the included accessories.')->assertSee('Listing passed the product review.')->assertDontSee('Sample data');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'active']);
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $product->id, 'action' => 'product.moderated', 'new_values->status' => $decision]);
    }

    public function test_product_approval_requires_an_active_store_and_valid_review_transition(): void
    {
        [, $product] = $this->listing('pending');
        $product->store->update(['status' => 'suspended']);
        $this->actingAs(User::factory()->administrator()->create())->patch(route('admin.products.moderate', $product), ['status' => 'active', 'reason' => 'The product is ready to publish.'])->assertSessionHasErrors('status');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'pending']);
        $product->store->update(['status' => 'active']);
        $product->update(['status' => 'rejected']);
        $this->patch(route('admin.products.moderate', $product), ['status' => 'active', 'reason' => 'The product is ready to publish.'])->assertSessionHasErrors('status');
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_only_the_owning_seller_can_submit_a_complete_listing(): void
    {
        [$seller, $product] = $this->listing('draft');
        [$otherSeller] = $this->listing();
        $this->actingAs($otherSeller)->post(route('seller.products.submit', $product))->assertNotFound();
        $product->update(['description' => null]);
        $this->actingAs($seller)->post(route('seller.products.submit', $product))->assertSessionHasErrors('status');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'draft']);
    }

    public function test_seller_shopping_uses_variant_prices_and_creates_separate_cod_store_orders(): void
    {
        [$shopper] = $this->listing();
        [, $first] = $this->listing();
        [, $second] = $this->listing();
        $variant = $first->variants()->create(['color' => 'Blue', 'size' => 'M', 'sku' => 'BLUE-M', 'stock_quantity' => 4, 'price' => '12.35']);
        $first->images()->create(['color' => 'Blue', 'position' => 1, 'path' => 'products/blue.jpg']);
        $second->update(['price' => '7.20']);
        $this->actingAs($shopper)->post(route('cart.store', $first), ['quantity' => 2, 'variant_id' => $variant->id])->assertRedirect();
        $this->post(route('cart.store', $second), ['quantity' => 1])->assertRedirect();
        $this->get(route('cart.index'))->assertSee('31.90')->assertSee('products/blue.jpg');
        $this->post(route('checkout.store'), $this->delivery())->assertRedirect();

        $order = Order::query()->with('sellerOrders.items', 'payment')->sole();
        $this->assertSame($shopper->id, $order->user_id);
        $this->assertSame(0, bccomp('31.90', (string) $order->total, 2));
        $this->assertCount(2, $order->sellerOrders);
        $this->assertSame('cod', $order->payment->method);
        $line = $order->sellerOrders->flatMap->items->firstWhere('product_id', $first->id);
        $this->assertSame('12.35', (string) $line->unit_price);
        $this->assertSame('products/blue.jpg', $line->image_path);
        $this->assertSame(2, $variant->fresh()->stock_quantity);
        $variant->update(['price' => '99.00']);
        $this->get(route('orders.show', $order))->assertSee('12.35')->assertSee('products/blue.jpg');
        $this->get(route('seller.orders.index'))->assertDontSee($order->number);
        $this->actingAs($first->tenant->members()->first()->user)->get(route('orders.show', $order))->assertNotFound();
    }

    public function test_stock_and_review_status_are_revalidated_without_partial_checkout(): void
    {
        $buyer = User::factory()->create();
        [, $otherProduct] = $this->listing();
        [, $product] = $this->listing();
        $variant = $product->variants()->create(['color' => 'Black', 'size' => 'M', 'sku' => 'BLACK-M', 'stock_quantity' => 2, 'price' => '11.00']);
        $this->actingAs($buyer)->post(route('cart.store', $otherProduct), ['quantity' => 1])->assertRedirect();
        $this->post(route('cart.store', $product), ['quantity' => 2, 'variant_id' => $variant->id])->assertRedirect();
        $this->post(route('cart.store', $product), ['quantity' => 1, 'variant_id' => $variant->id])->assertSessionHasErrors('quantity');
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 2]);
        $product->update(['status' => 'pending']);
        $this->post(route('checkout.store'), $this->delivery())->assertSessionHasErrors('cart');
        $this->assertSame(2, $variant->fresh()->stock_quantity);
        $this->assertSame(4, $otherProduct->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $product->update(['status' => 'active']);
        $variant->update(['stock_quantity' => 0]);
        $this->post(route('cart.store', $product), ['quantity' => 1, 'variant_id' => $variant->id])->assertSessionHasErrors('quantity');
        $this->post(route('checkout.store'), $this->delivery())->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_discussion_pagination_keeps_customer_and_store_replies_with_the_root(): void
    {
        [$seller, $product] = $this->listing();
        $buyer = User::factory()->create();
        $root = $product->discussions()->create(['user_id' => $buyer->id, 'author_type' => 'buyer', 'body' => 'Root question', 'created_at' => now()->subDay()]);
        for ($index = 0; $index < 20; $index++) {
            $product->discussions()->create(['user_id' => $buyer->id, 'author_type' => 'buyer', 'body' => 'Another question '.$index]);
        }
        $this->actingAs($seller)->post(route('discussions.store', $product), ['body' => 'Store answer after other threads.', 'parent_id' => $root->id])->assertRedirect();
        $this->get(route('catalog.show', [$product->store->slug, $product->slug]))->assertSeeInOrder(['Root question', 'Store answer after other threads.']);
        $this->get(route('catalog.show', [$product->store->slug, $product->slug, 'discussion_page' => 2]))->assertDontSee('Store answer after other threads.');
        $this->post(route('discussions.store', $product), ['body' => '   '])->assertSessionHasErrors('body');
    }

    /** @return array{0: User, 1: Product} */
    private function listing(string $status = 'active'): array
    {
        $seller = User::factory()->seller()->create();
        $suffix = strtolower((string) str()->ulid());
        $tenant = Tenant::create(['name' => 'Store '.$suffix, 'slug' => 'tenant-'.$suffix, 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => 'Store '.$suffix, 'slug' => 'store-'.$suffix, 'phone' => '09171234567', 'address_line' => '1 Market Street', 'city' => 'Cebu', 'status' => 'active']);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $seller->id, 'role' => 'owner']);
        $category = Category::firstOrCreate(['slug' => 'electronics'], ['name' => 'Electronics', 'is_active' => true]);
        $product = $store->products()->create(['tenant_id' => $tenant->id, 'category_id' => $category->id, 'name' => 'Product '.$suffix, 'slug' => 'product-'.$suffix, 'sku' => $suffix, 'description' => 'A complete product description.', 'price' => '10.00', 'stock_quantity' => 4, 'status' => $status]);
        $product->images()->create(['path' => 'products/shared.jpg', 'position' => 0]);

        return [$seller, $product];
    }

    /** @return array{recipient_name: string, phone: string, address_line: string, city: string, province: string, postal_code: string} */
    private function delivery(): array
    {
        return ['recipient_name' => 'Test Shopper', 'phone' => '09171234567', 'address_line' => '1 Market Street', 'city' => 'Cebu', 'province' => 'Cebu', 'postal_code' => '6000'];
    }
}
