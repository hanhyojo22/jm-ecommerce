<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_product_for_their_active_store(): void
    {
        [$owner, $tenant, $store] = $this->seller();

        $this->actingAs($owner)->post(route('seller.products.store'), [
            'name' => 'Canvas Tote', 'sku' => 'TOTE-001', 'description' => 'A durable tote.',
            'brand' => 'Aurora', 'condition' => 'refurbished', 'price' => '299.00', 'compare_at_price' => '399.00', 'stock_quantity' => 12, 'status' => 'active',
        ])->assertRedirect(route('seller.products.index'));

        $this->assertDatabaseHas('products', ['tenant_id' => $tenant->id, 'store_id' => $store->id, 'sku' => 'TOTE-001', 'brand' => 'Aurora', 'condition' => 'refurbished', 'compare_at_price' => '399.00', 'status' => 'draft']);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $owner->id, 'action' => 'product.created']);
    }

    public function test_seller_can_create_color_and_size_options_with_multiple_photos(): void
    {
        Storage::fake('product_images');
        [$owner, $tenant] = $this->seller();
        $this->actingAs($owner)->post(route('seller.products.store'), [
            'name' => 'Cotton shirt', 'sku' => 'SHIRT', 'price' => '499.00',
            'variants' => [
                ['color' => 'Navy', 'size' => 'M', 'sku' => 'SHIRT-N-M', 'stock_quantity' => 3],
                ['color' => 'Navy', 'size' => 'L', 'sku' => 'SHIRT-N-L', 'stock_quantity' => 2],
            ],
            'images' => [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('back.jpg')],
        ])->assertRedirect(route('seller.products.index'));

        $product = Product::query()->with(['variants', 'images'])->sole();
        $this->assertSame($tenant->id, $product->tenant_id);
        $this->assertSame(5, $product->stock_quantity);
        $this->assertCount(2, $product->variants);
        $this->assertCount(2, $product->images);
        foreach ($product->images as $image) {
            Storage::disk('product_images')->assertExists($image->path);
        }

        $product->update(['status' => 'active']);
        $this->get(route('catalog.show', [$product->store->slug, $product->slug]))
            ->assertOk()
            ->assertSee('Choose a product image')
            ->assertSeeInOrder(['Color:', 'Size:']);
    }

    public function test_seller_can_create_variants_with_only_one_option(): void
    {
        [$owner] = $this->seller();
        $this->actingAs($owner)->post(route('seller.products.store'), [
            'name' => 'Running socks', 'sku' => 'SOCK', 'price' => '149.00',
            'variants' => [
                ['size' => 'S', 'sku' => 'SOCK-S', 'stock_quantity' => 3],
                ['size' => 'M', 'sku' => 'SOCK-M', 'stock_quantity' => 2],
            ],
        ])->assertRedirect(route('seller.products.index'));

        $variants = Product::query()->sole()->variants;
        $this->assertEqualsCanonicalizing(['S', 'M'], $variants->map->label()->all());
        $this->assertSame('', $variants->first()->color);
    }

    public function test_seller_picks_a_swatch_for_a_color_variant(): void
    {
        [$owner] = $this->seller();
        $this->actingAs($owner)->post(route('seller.products.store'), [
            'name' => 'Cotton shirt', 'sku' => 'SHIRT', 'price' => '499.00',
            'variants' => [
                ['color' => 'Sunset', 'color_hex' => '#ff6633', 'sku' => 'SHIRT-SUNSET', 'stock_quantity' => 3],
                ['color' => 'Midnight Black', 'sku' => 'SHIRT-BLACK', 'stock_quantity' => 2],
            ],
        ])->assertRedirect(route('seller.products.index'));

        $variants = Product::query()->sole()->variants->keyBy('color');
        $this->assertSame('#ff6633', $variants['Sunset']->swatch());
        $this->assertSame(ProductVariant::SWATCHES['black'], $variants['Midnight Black']->swatch());

        $this->actingAs($owner)->post(route('seller.products.store'), [
            'name' => 'Cotton shirt', 'sku' => 'SHIRT-2', 'price' => '499.00',
            'variants' => [['color' => 'Red', 'color_hex' => 'red', 'sku' => 'SHIRT-RED', 'stock_quantity' => 1]],
        ])->assertSessionHasErrors('variants.0.color_hex');
    }

    public function test_variant_needs_a_color_or_a_size(): void
    {
        [$owner] = $this->seller();
        $this->actingAs($owner)->post(route('seller.products.store'), [
            'name' => 'Running socks', 'sku' => 'SOCK', 'price' => '149.00',
            'variants' => [['size' => 'S', 'sku' => 'SOCK-S', 'stock_quantity' => 1], ['stock_quantity' => 3]],
        ])->assertSessionHasErrors([
            'variants.1.color' => 'Variant 2 needs a color or a size.',
            'variants.1.sku' => 'The SKU for variant 2 field is required.',
        ]);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_seller_cannot_save_duplicate_color_and_size_options(): void
    {
        [$owner] = $this->seller();
        $this->actingAs($owner)->post(route('seller.products.store'), [
            'name' => 'Cotton shirt', 'sku' => 'SHIRT', 'price' => '499.00',
            'variants' => [
                ['color' => 'Navy', 'size' => 'M', 'sku' => 'SHIRT-N-M', 'stock_quantity' => 3],
                ['color' => 'navy', 'size' => 'm', 'sku' => 'SHIRT-N-M-2', 'stock_quantity' => 2],
            ],
        ])->assertSessionHasErrors('variants.1.color');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_seller_cannot_attach_another_products_variant_while_editing(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $product = $store->products()->create(['tenant_id' => $tenant->id, 'name' => 'Cotton shirt', 'slug' => 'cotton-shirt', 'sku' => 'SHIRT', 'price' => '499.00', 'stock_quantity' => 2, 'status' => 'draft']);
        $otherProduct = $store->products()->create(['tenant_id' => $tenant->id, 'name' => 'Other shirt', 'slug' => 'other-shirt', 'sku' => 'OTHER', 'price' => '399.00', 'stock_quantity' => 1, 'status' => 'draft']);
        $foreignVariant = $otherProduct->variants()->create(['color' => 'Red', 'size' => 'M', 'sku' => 'OTHER-R-M', 'stock_quantity' => 1]);

        $this->actingAs($owner)->patch(route('seller.products.update', $product), [
            'name' => 'Cotton shirt', 'sku' => 'SHIRT', 'price' => '499.00', 'status' => 'draft',
            'variants' => [['id' => $foreignVariant->id, 'color' => 'Navy', 'size' => 'L', 'sku' => 'SHIRT-N-L', 'stock_quantity' => 4]],
        ])->assertSessionHasErrors('variants.0.id');

        $this->assertDatabaseHas('product_variants', ['id' => $foreignVariant->id, 'product_id' => $otherProduct->id, 'color' => 'Red']);
        $this->assertSame(2, $product->refresh()->stock_quantity);
    }

    public function test_seller_can_update_option_stock_without_changing_the_product_price(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $product = $store->products()->create(['tenant_id' => $tenant->id, 'name' => 'Cotton shirt', 'slug' => 'cotton-shirt', 'sku' => 'SHIRT', 'price' => '499.00', 'stock_quantity' => 2, 'status' => 'draft']);
        $variant = $product->variants()->create(['color' => 'Navy', 'size' => 'M', 'sku' => 'SHIRT-N-M', 'stock_quantity' => 2]);

        $this->actingAs($owner)->patch(route('seller.products.update', $product), [
            'name' => 'Cotton shirt', 'sku' => 'SHIRT', 'price' => '499.00', 'status' => 'draft',
            'variants' => [['id' => $variant->id, 'color' => 'Navy', 'size' => 'M', 'sku' => 'SHIRT-N-M', 'stock_quantity' => 5]],
        ])->assertRedirect(route('seller.products.index'));

        $this->assertSame(5, $product->refresh()->stock_quantity);
        $this->assertSame(5, $variant->refresh()->stock_quantity);
        $this->assertSame('499.00', $product->price);
    }

    public function test_saved_color_variant_takes_a_size_when_sizes_are_added_later(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $product = $store->products()->create(['tenant_id' => $tenant->id, 'name' => 'Cotton shirt', 'slug' => 'cotton-shirt', 'sku' => 'SHIRT', 'price' => '499.00', 'stock_quantity' => 2, 'status' => 'draft']);
        $black = $product->variants()->create(['color' => 'Black', 'sku' => 'SHIRT-B', 'stock_quantity' => 2]);

        $this->actingAs($owner)->patch(route('seller.products.update', $product), [
            'name' => 'Cotton shirt', 'sku' => 'SHIRT', 'price' => '499.00', 'status' => 'draft',
            'variants' => [
                ['id' => $black->id, 'color' => 'Black', 'size' => 'M', 'sku' => 'SHIRT-B', 'stock_quantity' => 2],
                ['color' => 'Black', 'size' => 'L', 'sku' => 'SHIRT-B-L', 'stock_quantity' => 3],
            ],
        ])->assertRedirect(route('seller.products.index'));

        $this->assertSame(['L', 'M'], $product->variants()->pluck('size')->all());
        $this->assertSame('M', $black->refresh()->size);
        $this->assertSame(5, $product->refresh()->stock_quantity);
    }

    public function test_seller_cannot_view_another_tenants_product_listing(): void
    {
        [$owner] = $this->seller();
        [, , $otherStore] = $this->seller();
        $otherStore->products()->create(['tenant_id' => $otherStore->tenant_id, 'name' => 'Private Item', 'slug' => 'private-item', 'sku' => 'PRIVATE-1', 'price' => 10, 'stock_quantity' => 1, 'status' => 'active']);

        $this->actingAs($owner)->get(route('seller.products.index'))
            ->assertOk()->assertDontSee('Private Item');
    }

    public function test_product_catalog_links_to_tenant_scoped_details_with_real_variants(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $product = $store->products()->create(['tenant_id' => $tenant->id, 'name' => 'Canvas Tote', 'slug' => 'canvas-tote', 'sku' => 'TOTE-001', 'price' => '299.00', 'stock_quantity' => 3, 'status' => 'draft']);
        $product->variants()->create(['color' => 'Blue', 'size' => 'M', 'sku' => 'TOTE-BLUE', 'stock_quantity' => 3]);
        [, , $otherStore] = $this->seller();
        $otherProduct = $otherStore->products()->create(['tenant_id' => $otherStore->tenant_id, 'name' => 'Private Item', 'slug' => 'private-item', 'sku' => 'PRIVATE-1', 'price' => '100.00', 'stock_quantity' => 1, 'status' => 'draft']);

        $this->actingAs($owner)->get(route('seller.products.index'))
            ->assertSee('Product catalog')
            ->assertSee('1 variant')
            ->assertSee(route('seller.products.show', $product), false)
            ->assertDontSee('Private Item');
        $this->actingAs($owner)->get(route('seller.products.show', $product))
            ->assertSee('Canvas Tote')
            ->assertSee('TOTE-BLUE')
            ->assertSee('₱299.00');
        $this->actingAs($owner)->get(route('seller.products.show', $otherProduct))->assertNotFound();

        $staff = User::factory()->seller()->create();
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $staff->id, 'role' => 'staff']);
        $this->actingAs($staff)->get(route('seller.products.show', $product))
            ->assertSee('Canvas Tote')
            ->assertDontSee('Edit listing');
    }

    public function test_owner_can_assign_a_category_to_a_product(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $category = Category::create(['name' => 'Fashion', 'slug' => 'fashion', 'is_active' => true]);

        $this->actingAs($owner)->post(route('seller.products.store'), [
            'name' => 'Canvas Tote',
            'sku' => 'TOTE-001',
            'category_id' => $category->id,
            'price' => '299.00',
            'stock_quantity' => 12,
            'status' => 'active',
        ])->assertRedirect(route('seller.products.index'));

        $this->assertDatabaseHas('products', [
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_database_rejects_a_product_with_a_store_from_another_tenant(): void
    {
        [, $tenant] = $this->seller();
        [, , $otherStore] = $this->seller();

        $this->expectException(QueryException::class);

        Product::create([
            'tenant_id' => $tenant->id,
            'store_id' => $otherStore->id,
            'name' => 'Invalid product',
            'slug' => 'invalid-product',
            'sku' => 'INVALID-001',
            'price' => '100.00',
            'stock_quantity' => 1,
            'status' => 'active',
        ]);
    }

    public function test_product_creation_rejects_a_non_image_upload(): void
    {
        [$owner] = $this->seller();

        $this->actingAs($owner)->post(route('seller.products.store'), [
            'name' => 'Canvas Tote',
            'sku' => 'TOTE-001',
            'price' => '299.00',
            'stock_quantity' => 12,
            'status' => 'active',
            'image' => UploadedFile::fake()->create('payload.jpg', 10, 'text/plain'),
        ])->assertSessionHasErrors(['image']);
    }

    public function test_product_creation_stores_and_renders_an_uploaded_image(): void
    {
        Storage::fake('product_images');
        [$owner] = $this->seller();
        $image = UploadedFile::fake()->createWithContent(
            'canvas-tote.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL6TAAAAABJRU5ErkJggg=='),
        );

        $response = $this->actingAs($owner)->post(route('seller.products.store'), [
            'name' => 'Canvas Tote',
            'sku' => 'TOTE-001',
            'price' => '299.00',
            'stock_quantity' => 12,
            'status' => 'active',
            'image' => $image,
        ]);
        $product = Product::query()->with('images')->firstOrFail();
        $path = $product->images->firstOrFail()->path;

        $response->assertRedirect(route('seller.products.index'));
        Storage::disk('product_images')->assertExists($path);
        $this->actingAs($owner)
            ->get(route('seller.products.index'))
            ->assertSee(Storage::disk('product_images')->url($path));
    }

    public function test_product_creation_does_not_persist_when_image_storage_fails(): void
    {
        [$owner] = $this->seller();
        Storage::shouldReceive('disk')
            ->once()
            ->with('product_images')
            ->andThrow(new \RuntimeException('Image storage is unavailable.'));

        $this->actingAs($owner)->post(route('seller.products.store'), [
            'name' => 'Canvas Tote',
            'sku' => 'TOTE-001',
            'price' => '299.00',
            'stock_quantity' => 1,
            'status' => 'active',
            'image' => UploadedFile::fake()->createWithContent(
                'canvas-tote.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL6TAAAAABJRU5ErkJggg=='),
            ),
        ])->assertServerError();

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_product_update_does_not_persist_when_image_storage_fails(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $product = $store->products()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Canvas Tote',
            'slug' => 'canvas-tote',
            'sku' => 'TOTE-001',
            'price' => '299.00',
            'stock_quantity' => 1,
            'status' => 'active',
        ]);
        Storage::shouldReceive('disk')
            ->once()
            ->with('product_images')
            ->andThrow(new \RuntimeException('Image storage is unavailable.'));

        $this->actingAs($owner)->patch(route('seller.products.update', $product), [
            'name' => 'Canvas Tote Deluxe',
            'sku' => 'TOTE-001',
            'price' => '349.00',
            'stock_quantity' => 2,
            'status' => 'active',
            'image' => UploadedFile::fake()->createWithContent(
                'canvas-tote.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL6TAAAAABJRU5ErkJggg=='),
            ),
        ])->assertServerError();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Canvas Tote',
            'price' => '299.00',
            'stock_quantity' => 1,
            'status' => 'active',
        ]);
        $this->assertDatabaseCount('product_images', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_seller_can_remove_and_reorder_product_photos(): void
    {
        Storage::fake('product_images');
        [$owner, $tenant, $store] = $this->seller();
        $product = $this->draftProduct($tenant, $store);
        [$front, $side, $back] = collect(['front', 'side', 'back'])->map(fn (string $name, int $position) => $this->productPhoto($product, $name, $position))->all();

        $this->actingAs($owner)->patch(route('seller.products.update', $product), [
            ...$this->productPayload(),
            'remove_images' => [$side->id],
            'image_order' => [$back->id, $front->id],
        ])->assertRedirect(route('seller.products.index'));

        $this->assertModelMissing($side);
        Storage::disk('product_images')->assertMissing($side->path);
        $this->assertSame([$back->id, $front->id], $product->images()->pluck('id')->all());
        $this->assertSame($back->id, $product->fresh()->thumbnail->id);
    }

    public function test_removing_a_photo_keeps_the_file_while_a_past_order_shows_it(): void
    {
        Storage::fake('product_images');
        [$owner, $tenant, $store] = $this->seller();
        $product = $this->draftProduct($tenant, $store);
        $photo = $this->productPhoto($product, 'front', 0);
        $buyer = User::factory()->create();
        $order = Order::create(['user_id' => $buyer->id, 'number' => 'JM-1001', 'status' => 'delivered', 'recipient_name' => 'Casey Buyer', 'phone' => '+639171234567', 'address_line' => '100 Buyer Street', 'city' => 'Manila', 'subtotal' => '299.00', 'shipping_amount' => '0.00', 'total' => '299.00']);
        $order->sellerOrders()->create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'status' => 'delivered', 'subtotal' => '299.00', 'shipping_amount' => '0.00', 'total' => '299.00'])
            ->items()->create(['product_id' => $product->id, 'product_name' => 'Canvas Tote', 'sku' => 'TOTE-001', 'unit_price' => '299.00', 'quantity' => 1, 'line_total' => '299.00', 'image_path' => $photo->path]);

        $this->actingAs($owner)->patch(route('seller.products.update', $product), [...$this->productPayload(), 'remove_images' => [$photo->id]]);

        $this->assertModelMissing($photo);
        Storage::disk('product_images')->assertExists($photo->path);
    }

    public function test_seller_cannot_remove_another_products_photo(): void
    {
        Storage::fake('product_images');
        [$owner, $tenant, $store] = $this->seller();
        $product = $this->draftProduct($tenant, $store);
        [, $otherTenant, $otherStore] = $this->seller();
        $otherPhoto = $this->productPhoto($this->draftProduct($otherTenant, $otherStore), 'front', 0);

        $this->actingAs($owner)->patch(route('seller.products.update', $product), [...$this->productPayload(), 'remove_images' => [$otherPhoto->id]])
            ->assertSessionHasErrors('remove_images');

        $this->assertModelExists($otherPhoto);
    }

    public function test_removing_a_photo_makes_room_under_the_eight_photo_limit(): void
    {
        Storage::fake('product_images');
        [$owner, $tenant, $store] = $this->seller();
        $product = $this->draftProduct($tenant, $store);
        $photos = collect(range(0, 7))->map(fn (int $position) => $this->productPhoto($product, "photo-$position", $position));

        $this->actingAs($owner)->patch(route('seller.products.update', $product), [
            ...$this->productPayload(),
            'remove_images' => [$photos->first()->id],
            'images' => [UploadedFile::fake()->image('replacement.jpg')],
        ])->assertSessionHasNoErrors();

        $this->assertSame(8, $product->images()->count());
    }

    public function test_photo_uploads_cannot_push_a_product_past_eight_photos(): void
    {
        Storage::fake('product_images');
        [$owner, $tenant, $store] = $this->seller();
        $product = $this->draftProduct($tenant, $store);
        collect(range(0, 7))->each(fn (int $position) => $this->productPhoto($product, "photo-$position", $position));

        $this->actingAs($owner)->patch(route('seller.products.update', $product), [...$this->productPayload(), 'images' => [UploadedFile::fake()->image('extra.jpg')]])
            ->assertSessionHasErrors(['images' => 'A product can have no more than 8 photos. Remove a photo to add another.']);

        $this->assertSame(8, $product->images()->count());
    }

    public function test_product_creation_is_rate_limited_per_seller(): void
    {
        [$owner] = $this->seller();
        $payload = [
            'name' => 'Canvas Tote',
            'price' => '299.00',
            'stock_quantity' => 1,
            'status' => 'draft',
        ];

        for ($index = 1; $index <= 10; $index++) {
            $this->actingAs($owner)->post(route('seller.products.store'), [
                ...$payload,
                'sku' => "TOTE-{$index}",
            ])->assertRedirect(route('seller.products.index'));
        }

        $this->actingAs($owner)->post(route('seller.products.store'), [
            ...$payload,
            'sku' => 'TOTE-11',
        ])->assertTooManyRequests();

        $this->assertDatabaseCount('products', 10);
    }

    public function test_product_updates_are_rate_limited_per_seller(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $product = $store->products()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Canvas Tote',
            'slug' => 'canvas-tote',
            'sku' => 'TOTE-001',
            'price' => '299.00',
            'stock_quantity' => 1,
            'status' => 'active',
        ]);
        $payload = [
            'name' => 'Canvas Tote',
            'sku' => 'TOTE-001',
            'price' => '299.00',
            'stock_quantity' => 1,
            'status' => 'draft',
        ];

        for ($index = 0; $index < 10; $index++) {
            $this->actingAs($owner)->patch(route('seller.products.update', $product), $payload)
                ->assertRedirect(route('seller.products.index'));
        }

        $this->actingAs($owner)->patch(route('seller.products.update', $product), $payload)
            ->assertTooManyRequests();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Canvas Tote']);
    }

    public function test_seller_product_list_filters_by_search_status_and_stock(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $store->products()->create(['tenant_id' => $tenant->id, 'name' => 'Canvas Tote', 'slug' => 'canvas-tote', 'sku' => 'TOTE-001', 'price' => '299.00', 'stock_quantity' => 0, 'status' => 'draft']);
        $store->products()->create(['tenant_id' => $tenant->id, 'name' => 'Leather Wallet', 'slug' => 'leather-wallet', 'sku' => 'WALLET-001', 'price' => '499.00', 'stock_quantity' => 8, 'status' => 'active']);

        $this->actingAs($owner)->get(route('seller.products.index', [
            'search' => 'tote',
            'status' => 'draft',
            'stock' => 'out_of_stock',
            'sort' => 'name',
        ]))
            ->assertSee('Canvas Tote')
            ->assertDontSee('Leather Wallet')
            ->assertSee('Show all products')
            ->assertDontSee('More filters');
    }

    public function test_owner_can_update_their_own_product(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $product = $store->products()->create(['tenant_id' => $tenant->id, 'name' => 'Canvas Tote', 'slug' => 'canvas-tote', 'sku' => 'TOTE-001', 'price' => '299.00', 'stock_quantity' => 2, 'status' => 'active']);

        $this->actingAs($owner)->patch(route('seller.products.update', $product), [
            'name' => 'Canvas Tote Deluxe',
            'sku' => 'TOTE-001',
            'brand' => 'Field & Co.',
            'condition' => 'new',
            'price' => '349.00',
            'compare_at_price' => '449.00',
            'stock_quantity' => 7,
            'status' => 'active',
        ])->assertRedirect(route('seller.products.index'));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Canvas Tote Deluxe', 'brand' => 'Field & Co.', 'condition' => 'new', 'compare_at_price' => '449.00', 'stock_quantity' => 7, 'status' => 'active']);
        $this->assertNotNull($product->refresh()->content_changed_at);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $owner->id, 'action' => 'product.updated', 'entity_id' => $product->id]);
    }

    public function test_stock_and_price_edits_keep_a_live_listing_on_sale_without_a_re_check(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $product = $this->completeListing($tenant, $store, 'active');

        $this->actingAs($owner)->patch(route('seller.products.update', $product), [
            'name' => $product->name, 'sku' => $product->sku, 'description' => $product->description, 'category_id' => $product->category_id,
            'price' => '249.00', 'stock_quantity' => 9, 'status' => 'draft',
        ])->assertRedirect(route('seller.products.index'));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'active', 'price' => '249.00', 'stock_quantity' => 9, 'content_changed_at' => null]);
    }

    public function test_live_listing_cannot_lose_its_last_photo(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $product = $this->completeListing($tenant, $store, 'active');

        $this->actingAs($owner)->patch(route('seller.products.update', $product), [
            'name' => $product->name, 'sku' => $product->sku, 'description' => $product->description, 'category_id' => $product->category_id,
            'price' => '299.00', 'stock_quantity' => 2, 'status' => 'draft', 'remove_images' => [$product->images()->value('id')],
        ])->assertSessionHasErrors(['status' => 'A published listing needs a photo for every color, or a shared product photo.']);

        $this->assertSame(1, $product->images()->count());
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'active']);
    }

    public function test_seller_withdraws_a_submission_to_keep_editing(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $pending = $this->completeListing($tenant, $store, 'pending');
        $draft = $this->completeListing($tenant, $store, 'draft');

        $this->actingAs($owner)->post(route('seller.products.withdraw', $pending))
            ->assertSessionHas('status', 'Submission withdrawn. The listing is a draft again.');
        $this->post(route('seller.products.withdraw', $draft))
            ->assertSessionHasErrors(['status' => 'Only a listing under review can be withdrawn.']);

        $this->assertDatabaseHas('products', ['id' => $pending->id, 'status' => 'draft']);
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $pending->id, 'action' => 'product.withdrawn']);
    }

    public function test_seller_relists_an_unlisted_listing_without_review(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $unlisted = $this->completeListing($tenant, $store, 'unlisted');
        $draft = $this->completeListing($tenant, $store, 'draft');

        $this->actingAs($owner)->post(route('seller.products.relist', $unlisted))->assertRedirect(route('seller.products.index'));
        $this->post(route('seller.products.relist', $draft))
            ->assertSessionHasErrors(['status' => 'Only an unlisted listing can be relisted.']);

        $this->assertDatabaseHas('products', ['id' => $unlisted->id, 'status' => 'active']);
        $this->assertDatabaseHas('products', ['id' => $draft->id, 'status' => 'draft']);
    }

    public function test_seller_fixes_a_suspended_listing_and_submits_it_again(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $product = $this->completeListing($tenant, $store, 'inactive');

        $this->actingAs($owner)->patch(route('seller.products.update', $product), [
            'name' => 'Canvas Tote, cotton', 'sku' => $product->sku, 'description' => $product->description, 'category_id' => $product->category_id,
            'price' => '299.00', 'stock_quantity' => 2, 'status' => 'draft',
        ])->assertRedirect(route('seller.products.index'));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Canvas Tote, cotton', 'status' => 'inactive']);

        $this->post(route('seller.products.submit', $product))->assertRedirect(route('seller.products.index'));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'pending']);
    }

    public function test_seller_cannot_remove_required_details_from_a_complete_active_listing(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $category = Category::create(['name' => 'Bags', 'slug' => 'bags', 'is_active' => true]);
        $product = $store->products()->create(['tenant_id' => $tenant->id, 'category_id' => $category->id, 'name' => 'Canvas Tote', 'slug' => 'canvas-tote', 'sku' => 'TOTE-001', 'description' => 'A durable tote.', 'price' => '299.00', 'stock_quantity' => 2, 'status' => 'active']);
        $product->images()->create(['path' => 'products/tote.jpg', 'position' => 0]);

        $this->actingAs($owner)->patch(route('seller.products.update', $product), [
            'name' => 'Canvas Tote', 'sku' => 'TOTE-001', 'description' => '', 'category_id' => $category->id,
            'price' => '299.00', 'stock_quantity' => 2, 'status' => 'active',
        ])->assertSessionHasErrors('status');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'description' => 'A durable tote.', 'status' => 'active']);
    }

    public function test_seller_cannot_activate_a_draft_product(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $product = $store->products()->create(['tenant_id' => $tenant->id, 'name' => 'Canvas Tote', 'slug' => 'canvas-tote', 'sku' => 'TOTE-001', 'price' => '299.00', 'stock_quantity' => 2, 'status' => 'draft']);

        $this->actingAs($owner)->patch(route('seller.products.update', $product), [
            'name' => 'Canvas Tote',
            'sku' => 'TOTE-001',
            'price' => '299.00',
            'stock_quantity' => 2,
            'status' => 'active',
        ])->assertSessionHasErrors('status');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'draft']);
    }

    public function test_owner_can_render_the_edit_product_form(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $product = $store->products()->create(['tenant_id' => $tenant->id, 'name' => 'Canvas Tote', 'slug' => 'canvas-tote', 'sku' => 'TOTE-001', 'price' => '299.00', 'stock_quantity' => 2, 'status' => 'draft']);

        $this->actingAs($owner)
            ->get(route('seller.products.edit', $product))
            ->assertSee('Edit product')
            ->assertSee('Canvas Tote')
            ->assertSee('Save changes');
    }

    public function test_product_creation_rejects_a_regular_price_at_or_below_the_sale_price(): void
    {
        [$owner] = $this->seller();

        $this->actingAs($owner)->post(route('seller.products.store'), [
            'name' => 'Sale product',
            'sku' => 'SALE-001',
            'price' => '299.00',
            'compare_at_price' => '299.00',
            'stock_quantity' => 1,
            'status' => 'draft',
        ])->assertSessionHasErrors('compare_at_price');
    }

    public function test_product_creation_rejects_prices_with_more_than_two_decimal_places(): void
    {
        [$owner] = $this->seller();

        $this->actingAs($owner)->post(route('seller.products.store'), [
            'name' => 'Precision product',
            'sku' => 'PRECISION-001',
            'price' => '299.009',
            'compare_at_price' => '399.009',
            'stock_quantity' => 1,
            'status' => 'draft',
        ])->assertSessionHasErrors(['price', 'compare_at_price']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_product_update_rejects_prices_with_more_than_two_decimal_places(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $product = $store->products()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Precision product',
            'slug' => 'precision-product',
            'sku' => 'PRECISION-001',
            'price' => '299.00',
            'compare_at_price' => '399.00',
            'stock_quantity' => 1,
            'status' => 'draft',
        ]);

        $this->actingAs($owner)->patch(route('seller.products.update', $product), [
            'name' => 'Precision product',
            'sku' => 'PRECISION-001',
            'price' => '349.009',
            'compare_at_price' => '449.009',
            'stock_quantity' => 1,
            'status' => 'draft',
        ])->assertSessionHasErrors(['price', 'compare_at_price']);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'price' => '299.00',
            'compare_at_price' => '399.00',
        ]);
    }

    public function test_seller_cannot_update_another_tenants_product(): void
    {
        [$owner] = $this->seller();
        [, $otherTenant, $otherStore] = $this->seller();
        $product = $otherStore->products()->create(['tenant_id' => $otherTenant->id, 'name' => 'Private Item', 'slug' => 'private-item', 'sku' => 'PRIVATE-1', 'price' => '100.00', 'stock_quantity' => 1, 'status' => 'active']);

        $this->actingAs($owner)->patch(route('seller.products.update', $product), [
            'name' => 'Changed', 'sku' => 'PRIVATE-1', 'price' => '100.00', 'stock_quantity' => 1, 'status' => 'active',
        ])->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Private Item']);
    }

    public function test_owner_can_unlist_selected_active_products_from_their_store(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $products = collect(['Canvas Tote', 'Leather Wallet'])->map(fn (string $name): Product => $store->products()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'slug' => str()->slug($name),
            'sku' => str()->upper(str()->slug($name)),
            'price' => '299.00',
            'stock_quantity' => 5,
            'status' => 'active',
        ]));

        $this->actingAs($owner)->get(route('seller.products.index'))
            ->assertSee('Unlist selected')
            ->assertSee('name="product_ids[]"', false);

        $this->actingAs($owner)->post(route('seller.products.bulk-deactivate'), [
            'product_ids' => [...$products->pluck('id')->all(), ...$products->pluck('id')->all()],
        ])->assertRedirect(route('seller.products.index'))
            ->assertSessionHas('status', '2 listings were unlisted.');

        $this->assertDatabaseHas('products', ['id' => $products[0]->id, 'status' => 'unlisted']);
        $this->assertDatabaseHas('products', ['id' => $products[1]->id, 'status' => 'unlisted']);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_bulk_product_deactivation_rejects_foreign_or_non_active_products_atomically(): void
    {
        [$owner, $tenant, $store] = $this->seller();
        $ownedProduct = $store->products()->create(['tenant_id' => $tenant->id, 'name' => 'Canvas Tote', 'slug' => 'canvas-tote', 'sku' => 'TOTE-001', 'price' => '299.00', 'stock_quantity' => 5, 'status' => 'active']);
        $draftProduct = $store->products()->create(['tenant_id' => $tenant->id, 'name' => 'Draft Wallet', 'slug' => 'draft-wallet', 'sku' => 'DRAFT-001', 'price' => '199.00', 'stock_quantity' => 5, 'status' => 'draft']);
        [, $otherTenant, $otherStore] = $this->seller();
        $foreignProduct = $otherStore->products()->create(['tenant_id' => $otherTenant->id, 'name' => 'Private Item', 'slug' => 'private-item', 'sku' => 'PRIVATE-001', 'price' => '199.00', 'stock_quantity' => 5, 'status' => 'active']);

        $this->actingAs($owner)->post(route('seller.products.bulk-deactivate'), [
            'product_ids' => [$ownedProduct->id, $foreignProduct->id],
        ])->assertSessionHasErrors('product_ids');

        $this->actingAs($owner)->post(route('seller.products.bulk-deactivate'), [
            'product_ids' => [$ownedProduct->id, $draftProduct->id],
        ])->assertSessionHasErrors('product_ids');

        $this->assertDatabaseHas('products', ['id' => $ownedProduct->id, 'status' => 'active']);
        $this->assertDatabaseHas('products', ['id' => $draftProduct->id, 'status' => 'draft']);
        $this->assertDatabaseHas('products', ['id' => $foreignProduct->id, 'status' => 'active']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_staff_cannot_deactivate_listings_in_bulk(): void
    {
        [, $tenant] = $this->seller();
        $staff = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $staff->id, 'role' => 'staff']);

        $this->actingAs($staff)->post(route('seller.products.bulk-deactivate'), [
            'product_ids' => ['01J00000000000000000000000'],
        ])->assertForbidden();
    }

    private function draftProduct(Tenant $tenant, Store $store): Product
    {
        return $store->products()->create(['tenant_id' => $tenant->id, 'name' => 'Canvas Tote', 'slug' => 'canvas-tote-'.str()->lower(str()->random(6)), 'sku' => 'TOTE-'.str()->upper(str()->random(6)), 'price' => '299.00', 'stock_quantity' => 1, 'status' => 'draft']);
    }

    private function productPhoto(Product $product, string $name, int $position): ProductImage
    {
        $path = "products/{$product->id}-{$name}.jpg";
        Storage::disk('product_images')->put($path, 'photo');

        return $product->images()->create(['path' => $path, 'position' => $position]);
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(): array
    {
        return ['name' => 'Canvas Tote', 'sku' => 'TOTE-001', 'price' => '299.00', 'stock_quantity' => 1, 'status' => 'draft'];
    }

    private function completeListing(Tenant $tenant, Store $store, string $status): Product
    {
        $category = Category::firstOrCreate(['slug' => 'bags'], ['name' => 'Bags', 'is_active' => true]);
        $product = $store->products()->create(['tenant_id' => $tenant->id, 'category_id' => $category->id, 'name' => 'Canvas Tote', 'slug' => 'canvas-tote-'.str()->lower(str()->random(6)), 'sku' => 'TOTE-'.str()->upper(str()->random(6)), 'description' => 'A durable tote.', 'price' => '299.00', 'stock_quantity' => 2, 'status' => $status]);
        $product->images()->create(['path' => 'products/tote.jpg', 'position' => 0]);

        return $product;
    }

    private function seller(): array
    {
        $owner = User::factory()->seller()->create(['email_verified_at' => now()]);
        $tenant = Tenant::create(['name' => 'Northwind', 'slug' => 'northwind-'.str()->lower(str()->random(6)), 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => 'Northwind Goods '.str()->random(6), 'slug' => 'northwind-goods-'.str()->lower(str()->random(6)), 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner']);

        return [$owner, $tenant, $store];
    }
}
