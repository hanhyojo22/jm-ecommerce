<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use App\Support\CookiePreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_suggestions_rank_direct_names_and_limit_results(): void
    {
        $exact = $this->product(['name' => 'Arc']);
        $prefix = $this->product(['name' => 'Arc Keyboard']);
        $word = $this->product(['name' => 'Travel Arc Headset']);
        $ranked = $this->getJson(route('catalog.suggestions', ['q' => 'arc']))->assertOk()->json('items');
        $this->assertSame([$exact->name, $prefix->name, $word->name], array_column($ranked, 'name'));

        foreach (range(1, 9) as $number) {
            $this->product(['name' => "Arc accessory {$number}"]);
        }

        $items = $this->getJson(route('catalog.suggestions', ['q' => 'arc']))
            ->assertOk()
            ->assertJsonCount(8, 'items')
            ->json('items');

        $this->assertSame($exact->name, $items[0]['name']);
        $this->assertContains($prefix->name, array_column($items, 'name'));
        $this->assertNotContains($word->name, array_column($items, 'name'));
        $this->getJson(route('catalog.suggestions', ['q' => 'a']))->assertJsonPath('items', []);
        $this->getJson(route('catalog.suggestions', ['q' => 'arc%']))->assertJsonPath('items', []);
    }

    public function test_product_suggestions_only_include_approved_public_products_from_active_stores(): void
    {
        $visible = $this->product(['name' => 'Cedar Lamp']);
        foreach (['draft', 'pending', 'changes_required', 'rejected', 'inactive'] as $status) {
            $this->product(['name' => "Cedar {$status} Lamp", 'status' => $status]);
        }
        $this->product(['name' => 'Cedar inactive store'], 'active', 'inactive');
        $this->product(['name' => 'Cedar suspended tenant'], 'suspended');
        $deleted = $this->product(['name' => 'Cedar deleted Lamp']);
        $deleted->delete();

        $this->getJson(route('catalog.suggestions', ['q' => 'cedar']))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.name', $visible->name)
            ->assertJsonPath('items.0.store', $visible->store->name);
    }

    public function test_product_suggestions_show_variant_price_image_and_sold_out_status(): void
    {
        $available = $this->product(['name' => 'Copper Headphones', 'price' => '299.00', 'stock_quantity' => 2]);
        $available->variants()->create(['color' => 'Black', 'size' => 'Standard', 'sku' => 'COPPER-BLACK', 'stock_quantity' => 2, 'price' => '249.50']);
        $available->images()->create(['path' => 'products/copper.jpg', 'position' => 0]);
        $soldOut = $this->product(['name' => 'Copper Speaker', 'stock_quantity' => 0]);
        $soldOut->variants()->create(['color' => 'Blue', 'size' => 'Standard', 'sku' => 'COPPER-BLUE', 'stock_quantity' => 0, 'price' => null]);

        $items = $this->getJson(route('catalog.suggestions', ['q' => 'copper']))->assertOk()->json('items');

        $this->assertSame('₱249.50', $items[0]['price']);
        $this->assertFalse($items[0]['sold_out']);
        $this->assertStringContainsString('products/copper.jpg', $items[0]['image']);
        $this->assertSame(route('catalog.show', [$available->store->slug, $available->slug]), $items[0]['url']);
        $this->assertTrue($items[1]['sold_out']);
        $this->assertSame('₱199.00', $items[1]['price']);
    }

    public function test_product_page_exposes_search_metadata_for_a_published_listing(): void
    {
        $product = $this->product(['description' => 'A useful canvas tote.', 'price' => '19.99']);
        $product->images()->create(['path' => 'products/tote.jpg', 'sort_order' => 0]);

        $this->get(route('catalog.show', [$product->store->slug, $product->slug]))
            ->assertOk()
            ->assertSee('<meta name="description" content="A useful canvas tote.">', false)
            ->assertSee('<link rel="canonical" href="'.route('catalog.show', [$product->store->slug, $product->slug]).'">', false)
            ->assertSee('application/ld+json')
            ->assertSee('InStock');
    }

    public function test_long_product_description_can_expand_without_a_generated_preview(): void
    {
        $longDescription = str_repeat('RGB lighting with tactile mechanical switches and aluminum housing. ', 6).'Includes a detachable wrist rest.';
        $longProduct = $this->product(['description' => $longDescription]);

        $this->get(route('catalog.show', [$longProduct->store->slug, $longProduct->slug]))
            ->assertSee('<details class="pd-description-disclosure">', false)
            ->assertSee('Read product description')
            ->assertDontSee('pd-description-preview')
            ->assertSee('Includes a detachable wrist rest.');

        $shortProduct = $this->product(['description' => 'A compact keyboard.']);

        $this->get(route('catalog.show', [$shortProduct->store->slug, $shortProduct->slug]))
            ->assertSee('A compact keyboard.')
            ->assertDontSee('pd-description-disclosure');
    }

    public function test_homepage_shows_categories_and_only_public_products(): void
    {
        $category = Category::create(['name' => 'Home essentials', 'slug' => 'home-essentials', 'is_active' => true]);
        $visible = $this->product(['category_id' => $category->id]);
        $hidden = $this->product(['name' => 'Unpublished product', 'status' => 'draft', 'category_id' => $category->id]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Find your kind of good.')
            ->assertSee($category->name)
            ->assertSee('id="marketplace-search"', false)
            ->assertSee('Mobile marketplace navigation')
            ->assertSee($visible->name)
            ->assertDontSee($hidden->name);
    }

    public function test_sold_out_product_shows_availability_notice_across_public_product_pages(): void
    {
        $product = $this->product(['stock_quantity' => 0]);

        foreach ([
            route('home'),
            route('catalog.index'),
            route('catalog.store', $product->store->slug),
            route('catalog.show', [$product->store->slug, $product->slug]),
        ] as $url) {
            $this->get($url)
                ->assertSee($product->name)
                ->assertSee('This product is currently unavailable');
        }
    }

    public function test_available_product_does_not_show_sold_out_image_notice(): void
    {
        $product = $this->product(['stock_quantity' => 3]);

        foreach ([
            route('home'),
            route('catalog.index'),
            route('catalog.store', $product->store->slug),
            route('catalog.show', [$product->store->slug, $product->slug]),
        ] as $url) {
            $this->get($url)->assertDontSee('This product is currently unavailable');
        }
    }

    public function test_homepage_shows_live_flash_deals_and_buyer_cart_actions(): void
    {
        $buyer = User::factory()->create();
        $deal = $this->product(['name' => 'Marketplace sale item', 'price' => 149, 'compare_at_price' => 199]);

        $this->actingAs($buyer)->get(route('home'))
            ->assertOk()
            ->assertSee('Flash deals')
            ->assertSee($deal->name)
            ->assertSee(route('cart.store', $deal), false)
            ->assertSee('Add to cart');
    }

    public function test_homepage_hides_popular_picks_without_viewing_history(): void
    {
        $product = $this->product();
        $this->attachReview($product, false);

        $this->get(route('home'))
            ->assertSee('Trending products')
            ->assertDontSee('Popular picks')
            ->assertDontSee('home-recommended-heading');
    }

    public function test_homepage_loads_new_arrivals_in_public_batches_of_twenty(): void
    {
        $newest = $this->product(['name' => 'Arrival 41']);
        foreach (range(40, 1) as $number) {
            $product = new Product([
                'tenant_id' => $newest->tenant_id,
                'store_id' => $newest->store_id,
                'name' => "Arrival {$number}",
                'slug' => "arrival-{$number}",
                'sku' => "ARRIVAL-{$number}",
                'price' => 199,
                'stock_quantity' => 3,
                'status' => 'active',
            ]);
            $product->created_at = now()->subMinutes(41 - $number);
            $product->save();
        }
        $this->product(['name' => 'Hidden arrival', 'status' => 'draft']);

        $firstPage = $this->get(route('home'));
        $firstPage
            ->assertSee('Arrival 41')
            ->assertSee('Arrival 22')
            ->assertDontSee('Arrival 21')
            ->assertDontSee('Hidden arrival')
            ->assertSee('data-new-arrivals-more', false);
        $nextUrl = $firstPage->viewData('newProducts')->nextPageUrl();
        $this->assertNotNull($nextUrl);
        $this->assertStringStartsWith('/?new_cursor=', $nextUrl);

        $this->product(['name' => 'Later arrival']);

        $secondPage = $this->getJson($nextUrl);
        $secondPage
            ->assertOk()
            ->assertSee('Arrival 21')
            ->assertDontSee('Arrival 41')
            ->assertDontSee('Arrival 22')
            ->assertDontSee('Later arrival')
            ->assertDontSee('Hidden arrival')
            ->assertJsonStructure(['html', 'next_url']);
        $this->assertStringStartsWith('/?new_cursor=', $secondPage->json('next_url'));
        $this->get($nextUrl)
            ->assertSee('Arrival 21')
            ->assertDontSee('Arrival 22');
        $this->getJson($secondPage->json('next_url'))
            ->assertOk()
            ->assertSee('Arrival 1')
            ->assertJsonPath('next_url', null);
    }

    public function test_new_arrivals_rejects_invalid_cursors(): void
    {
        $this->getJson(route('home', ['new_cursor' => 'invalid']))->assertUnprocessable();
        $this->getJson(route('home', ['new_cursor' => base64_encode(json_encode(['id' => 'bad', '_pointsToNextItems' => true]))]))->assertUnprocessable();
    }

    public function test_homepage_checks_shopper_store_membership_once_for_product_cards(): void
    {
        $buyer = User::factory()->create();
        for ($number = 1; $number <= 5; $number++) {
            $this->product(['name' => "Membership card {$number}"]);
        }
        $membershipQueries = [];
        DB::listen(function ($query) use (&$membershipQueries): void {
            if (str_contains($query->sql, 'tenant_members')) {
                $membershipQueries[] = $query->sql;
            }
        });

        $this->actingAs($buyer)->get(route('home'))->assertSee('Membership card 1');

        $this->assertCount(1, $membershipQueries);
    }

    public function test_homepage_tracks_recently_viewed_products_and_recommends_their_category(): void
    {
        $category = Category::create(['name' => 'Audio', 'slug' => 'audio', 'is_active' => true]);
        $viewed = $this->product(['name' => 'Viewed headphones', 'category_id' => $category->id]);
        $related = $this->product(['name' => 'Related speaker', 'category_id' => $category->id]);

        $this->withCookie(CookiePreferences::COOKIE_NAME, json_encode([
            'version' => CookiePreferences::VERSION,
            'functional' => true,
            'analytics' => false,
            'marketing' => false,
        ]))->get(route('catalog.show', [$viewed->store->slug, $viewed->slug]))
            ->assertOk()
            ->assertSessionHas('marketplace.recently_viewed', [$viewed->id]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Recommended for you')
            ->assertSee('Recently viewed')
            ->assertSee($viewed->name)
            ->assertSee($related->name)
            ->assertSee('Clear history');

        $this->delete(route('catalog.recently-viewed.clear'))
            ->assertRedirect(route('home'))
            ->assertSessionMissing('marketplace.recently_viewed');
    }

    public function test_recently_viewed_is_not_saved_without_functional_consent(): void
    {
        $product = $this->product();

        $this->get(route('catalog.show', [$product->store->slug, $product->slug]))
            ->assertOk()
            ->assertSessionMissing('marketplace.recently_viewed');
    }

    public function test_catalog_only_shows_active_products_from_active_stores_and_tenants(): void
    {
        $visible = $this->product();
        $hiddenProduct = $this->product(['status' => 'draft']);
        $hiddenStore = $this->product(storeStatus: 'inactive');
        $hiddenTenant = $this->product(tenantStatus: 'suspended');

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('data-module-url=', false)
            ->assertSee($visible->name)
            ->assertDontSee($hiddenProduct->name)
            ->assertDontSee($hiddenStore->name)
            ->assertDontSee($hiddenTenant->name);
    }

    public function test_guest_catalog_keeps_the_sign_in_link_visible_on_small_screens(): void
    {
        $this->get(route('catalog.index'))
            ->assertSee(route('login'), false)
            ->assertSee('aria-label="Sign In"', false)
            ->assertSee('min-[641px]:inline">Sign In</span>', false)
            ->assertDontSee('aria-label="Account menu"', false)
            ->assertSee('Sign in')
            ->assertSee('aria-label="Open navigation menu"', false);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('register'), false)
            ->assertSee('Create account');
    }

    public function test_marketplace_pages_include_the_clerk_configuration_needed_by_the_shared_header(): void
    {
        $product = $this->product();

        foreach ([
            route('catalog.index'),
            route('catalog.stores'),
            route('catalog.top-products'),
            route('catalog.show', [$product->store->slug, $product->slug]),
        ] as $url) {
            $this->get($url)->assertSee('name="clerk-publishable-key"', false);
        }
    }

    public function test_customer_account_pages_keep_the_marketplace_header(): void
    {
        $buyer = User::factory()->create();

        foreach ([route('profile.edit'), route('orders.index'), route('cart.index')] as $url) {
            $this->actingAs($buyer)->get($url)
                ->assertOk()
                ->assertSee('id="marketplace-search"', false)
                ->assertSee($url === route('cart.index') ? 'aria-label="Marketplace sections"' : 'aria-label="My account navigation"', false)
                ->assertSee($url === route('cart.index') ? 'data-header-promo' : 'data-header-role="customer-workspace"', false)
                ->assertSee('aria-label="Account menu"', false)
                ->assertDontSee('aria-label="Sign In"', false);
        }
    }

    public function test_signed_in_roles_keep_their_account_controls_in_the_header(): void
    {
        foreach ([
            User::factory()->create(),
            User::factory()->seller()->create(),
            User::factory()->administrator()->create(),
        ] as $user) {
            $this->actingAs($user)->get(route('home'))
                ->assertOk()
                ->assertSee('aria-label="Account menu"', false)
                ->assertDontSee('aria-label="Sign In"', false);
        }

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-header-role="admin"', false)
            ->assertSee('Account settings')
            ->assertDontSee('aria-label="Sign In"', false);
    }

    public function test_catalog_product_page_404s_for_a_non_public_product(): void
    {
        $product = $this->product(['status' => 'draft']);

        $this->get(route('catalog.show', [$product->store->slug, $product->slug]))->assertNotFound();
    }

    public function test_catalog_product_page_renders_its_primary_image(): void
    {
        Storage::fake('product_images');
        $product = $this->product();
        $path = 'products/canvas-tote.png';
        $secondPath = 'products/canvas-tote-back.png';
        Storage::disk('product_images')->put($path, 'image');
        Storage::disk('product_images')->put($secondPath, 'image');
        $product->images()->create(['path' => $path]);
        $product->images()->create(['path' => $secondPath, 'position' => 1]);

        $this->get(route('catalog.show', [$product->store->slug, $product->slug]))
            ->assertOk()
            ->assertSee('data-module-url=', false)
            ->assertSee(Storage::disk('product_images')->url($path))
            ->assertSee('Choose a product image')
            ->assertSee('aria-label="Previous product image"', false)
            ->assertSee('aria-label="Next product image"', false)
            ->assertSee('aria-label="Enlarged product image"', false);
    }

    public function test_verified_buyer_sees_add_to_cart_actions_in_the_catalog(): void
    {
        $buyer = User::factory()->create();
        $product = $this->product();

        $this->actingAs($buyer)->get(route('catalog.index'))
            ->assertSee('Cart')
            ->assertSee('Add to cart')
            ->assertSee('aria-controls="header-account-menu"', false)
            ->assertSee('data-module-url=', false)
            ->assertSee(route('cart.store', $product))
            ->assertSee(route('profile.edit'));
    }

    public function test_marketplace_search_matches_a_public_store_name(): void
    {
        $product = $this->product(['name' => 'Unrelated product']);

        $this->get(route('catalog.index', ['q' => $product->store->name]))
            ->assertSee($product->name);
    }

    public function test_store_directory_links_guests_to_public_storefronts(): void
    {
        $product = $this->product();

        $this->get(route('catalog.stores'))
            ->assertSee('data-module-url=', false)
            ->assertSee($product->store->name)
            ->assertSee(route('catalog.store', $product->store->slug), false);

        $this->get(route('catalog.store', $product->store->slug))
            ->assertSee($product->store->name);
    }

    public function test_catalog_filters_products_by_category_and_sorts_them(): void
    {
        $category = Category::create(['name' => 'Bags', 'slug' => 'bags', 'is_active' => true]);
        $categorizedProduct = $this->product(['name' => 'Canvas Market Bag', 'category_id' => $category->id, 'price' => 199]);
        $otherProduct = $this->product(['name' => 'Handmade Walnut Desk', 'price' => 9999]);

        $this->get(route('catalog.index', ['category' => $category->slug, 'sort' => 'price_asc']))
            ->assertSee($categorizedProduct->name)
            ->assertDontSee($otherProduct->name)
            ->assertSee('value="price_asc" selected', false);
    }

    public function test_catalog_sorts_by_published_rating_then_review_count(): void
    {
        $highestRated = $this->product(['name' => 'Highest rated product']);
        $lowerRated = $this->product(['name' => 'Lower rated product']);
        $unrated = $this->product(['name' => 'Unrated product']);
        $this->attachReview($highestRated, false);
        $this->attachReview($lowerRated, false);
        ProductReview::query()->where('product_id', $lowerRated->id)->update(['rating' => 3]);

        $this->get(route('catalog.index', ['sort' => 'top_rated']))
            ->assertOk()
            ->assertSeeInOrder([$highestRated->name, $lowerRated->name, $unrated->name])
            ->assertSee('value="top_rated" selected', false);
    }

    public function test_catalog_custom_price_filter_uses_inclusive_bounds(): void
    {
        $belowRange = $this->product(['name' => 'Below the price band', 'price' => 24999.99]);
        $lowerBoundary = $this->product(['name' => 'At lower price boundary', 'price' => 25000]);
        $upperBoundary = $this->product(['name' => 'At upper price boundary', 'price' => 100000]);
        $aboveRange = $this->product(['name' => 'Above the price band', 'price' => 100000.01]);

        $this->get(route('catalog.index', ['min_price' => '25000', 'max_price' => '100000']))
            ->assertOk()
            ->assertSee($lowerBoundary->name)
            ->assertSee($upperBoundary->name)
            ->assertDontSee($belowRange->name)
            ->assertDontSee($aboveRange->name)
            ->assertSee('name="min_price"', false)
            ->assertSee('name="max_price"', false);
    }

    public function test_catalog_rejects_an_invalid_minimum_price(): void
    {
        $this->get(route('catalog.index', ['min_price' => 'unexpected']))
            ->assertRedirect()
            ->assertSessionHasErrors('min_price');
    }

    public function test_catalog_seller_filter_only_shows_products_from_the_selected_public_store(): void
    {
        $selectedProduct = $this->product(['name' => 'Selected Store Lamp']);
        $otherProduct = $this->product(['name' => 'Other Store Shelf']);
        $inactiveStoreProduct = $this->product(['name' => 'Inactive Store Vase'], storeStatus: 'inactive');

        $this->get(route('catalog.index', ['seller' => $selectedProduct->store->slug]))
            ->assertOk()
            ->assertSee($selectedProduct->name)
            ->assertDontSee($otherProduct->name)
            ->assertDontSee($inactiveStoreProduct->name)
            ->assertSee('name="seller" value="'.$selectedProduct->store->slug.'"', false);
    }

    public function test_catalog_search_matches_product_brands(): void
    {
        $matchingProduct = $this->product(['name' => 'Noise cancelling headphones', 'brand' => 'Aurora']);
        $otherProduct = $this->product(['name' => 'Portable speaker', 'brand' => 'Volt']);

        $this->get(route('catalog.index', ['q' => 'Aurora']))
            ->assertOk()
            ->assertSee($matchingProduct->name)
            ->assertDontSee($otherProduct->name);
    }

    public function test_catalog_filters_products_by_brand_condition_and_sale_status(): void
    {
        $matchingProduct = $this->product([
            'name' => 'Aurora refurbished headphones',
            'brand' => 'Aurora',
            'condition' => 'refurbished',
            'compare_at_price' => '250.00',
            'price' => '200.00',
        ]);
        $otherProduct = $this->product([
            'name' => 'Volt new headphones',
            'brand' => 'Volt',
            'condition' => 'new',
            'price' => '200.00',
        ]);
        $fractionalProduct = $this->product([
            'name' => 'Aurora fractional price headphones',
            'brand' => 'Aurora',
            'condition' => 'refurbished',
            'compare_at_price' => '250.75',
            'price' => '200.50',
        ]);

        $this->get(route('catalog.index', [
            'brands' => ['Aurora'],
            'conditions' => ['refurbished'],
            'on_sale' => '1',
        ]))
            ->assertOk()
            ->assertSee($matchingProduct->name)
            ->assertSee($fractionalProduct->name)
            ->assertDontSee($otherProduct->name)
            ->assertSee('name="brands[]" value="Aurora" checked', false)
            ->assertSee('name="conditions[]" value="refurbished" checked', false)
            ->assertSee('-20%')
            ->assertSee('₱200</p>', false)
            ->assertSee('₱250</del>', false)
            ->assertSee('₱200.50')
            ->assertSee('₱250.75');
    }

    public function test_catalog_rejects_an_unknown_product_condition_filter(): void
    {
        $this->get(route('catalog.index', ['conditions' => ['used']]))
            ->assertRedirect()
            ->assertSessionHasErrors('conditions.0');
    }

    public function test_catalog_with_photos_filter_only_shows_products_with_published_photo_reviews(): void
    {
        $withPhotos = $this->product(['name' => 'Photo reviewed product']);
        $withoutPhotos = $this->product(['name' => 'Text only reviewed product']);
        $hiddenPhotoReview = $this->product(['name' => 'Hidden photo review product']);
        $this->attachReview($withPhotos, true);
        $this->attachReview($withoutPhotos, false);
        $this->attachReview($hiddenPhotoReview, true, 'hidden');

        $this->get(route('catalog.index', ['with_photos' => '1']))
            ->assertOk()
            ->assertSee($withPhotos->name)
            ->assertDontSee($withoutPhotos->name)
            ->assertDontSee($hiddenPhotoReview->name)
            ->assertSee('name="with_photos" value="1" checked', false)
            ->assertSee('With photos');
    }

    public function test_catalog_rejects_a_non_boolean_photo_review_filter(): void
    {
        $this->get(route('catalog.index', ['with_photos' => 'sometimes']))
            ->assertRedirect()
            ->assertSessionHasErrors('with_photos');
    }

    public function test_catalog_opens_with_marketplace_filters_and_product_grid(): void
    {
        $this->product();

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('Account')
            ->assertSee('aria-label="Sign in to view cart"', false)
            ->assertSee('catalog-filters', false)
            ->assertDontSee('price_min', false)
            ->assertDontSee('name="seller"', false)
            ->assertDontSee('Apply filters')
            ->assertSee('x-on:change="$el.form.requestSubmit()"', false)
            ->assertSee('Marketplace')
            ->assertDontSee('Everything you love, from stores you can trust.')
            ->assertSee('min-[1021px]:grid-cols-4', false);
    }

    public function test_catalog_shows_purchased_units_and_excludes_cancelled_seller_orders(): void
    {
        $product = $this->product();
        $buyer = User::factory()->create();
        $suffix = str()->lower(str()->random(8));
        $confirmedOrder = Order::create(['user_id' => $buyer->id, 'number' => "ORD-{$suffix}-confirmed", 'status' => 'confirmed', 'recipient_name' => 'Buyer', 'phone' => '+639171234567', 'address_line' => '1 Market Street', 'city' => 'Manila', 'subtotal' => 238800, 'shipping_amount' => 0, 'total' => 238800]);
        $confirmedSellerOrder = $confirmedOrder->sellerOrders()->create(['tenant_id' => $product->tenant_id, 'store_id' => $product->store_id, 'status' => 'confirmed', 'subtotal' => 238800, 'shipping_amount' => 0, 'total' => 238800]);
        $confirmedSellerOrder->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku, 'unit_price' => 199, 'quantity' => 1200, 'line_total' => 238800]);
        $cancelledOrder = Order::create(['user_id' => $buyer->id, 'number' => "ORD-{$suffix}-cancelled", 'status' => 'cancelled', 'recipient_name' => 'Buyer', 'phone' => '+639171234567', 'address_line' => '1 Market Street', 'city' => 'Manila', 'subtotal' => 79600, 'shipping_amount' => 0, 'total' => 79600]);
        $cancelledSellerOrder = $cancelledOrder->sellerOrders()->create(['tenant_id' => $product->tenant_id, 'store_id' => $product->store_id, 'status' => 'cancelled', 'subtotal' => 79600, 'shipping_amount' => 0, 'total' => 79600]);
        $cancelledSellerOrder->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku, 'unit_price' => 199, 'quantity' => 400, 'line_total' => 79600]);

        $this->get(route('catalog.index'))
            ->assertSee('1.2k sold')
            ->assertDontSee('1.6k sold');
    }

    public function test_catalog_pagination_preserves_category_and_sort_queries(): void
    {
        $category = Category::create(['name' => 'Office', 'slug' => 'office', 'is_active' => true]);

        foreach (range(1, 13) as $number) {
            $this->product(['name' => "Office product {$number}", 'category_id' => $category->id, 'price' => $number]);
        }

        $this->get(route('catalog.index', ['category' => $category->slug, 'sort' => 'price_asc']))
            ->assertOk()
            ->assertSee('category=office&amp;sort=price_asc&amp;page=2', false)
            ->assertSee('aria-label="Pagination Navigation"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('size-10', false)
            ->assertSee('bg-ink text-sm font-semibold text-white', false);
    }

    public function test_catalog_category_navigation_shows_active_categories(): void
    {
        $visibleCategory = Category::create(['name' => 'Electronics', 'slug' => 'electronics', 'is_active' => true]);
        $secondVisibleCategory = Category::create(['name' => 'Fashion', 'slug' => 'fashion', 'is_active' => true]);
        $inactiveCategory = Category::create(['name' => 'Archived category', 'slug' => 'archived-category', 'is_active' => false]);
        $draftCategory = Category::create(['name' => 'Draft category', 'slug' => 'draft-category', 'is_active' => true]);

        $this->product(['category_id' => $visibleCategory->id]);
        $this->product(['category_id' => $visibleCategory->id]);
        $this->product(['category_id' => $secondVisibleCategory->id]);
        $this->product(['category_id' => $inactiveCategory->id]);
        $this->product(['category_id' => $draftCategory->id, 'status' => 'draft']);

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('Category')
            ->assertSee('Electronics')
            ->assertSee('Draft category')
            ->assertDontSee('Because you are alive', false)
            ->assertDontSee('Archived category');
    }

    public function test_catalog_hides_the_category_explorer_when_only_one_category_is_available(): void
    {
        $category = Category::create(['name' => 'Electronics', 'slug' => 'electronics', 'is_active' => true]);
        $this->product(['category_id' => $category->id]);

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertDontSee('Shop by category')
            ->assertDontSee('All categories');
    }

    public function test_seller_storefront_shows_only_that_sellers_products(): void
    {
        $storefrontProduct = $this->product(['name' => 'Storefront Espresso Cup']);
        $otherProduct = $this->product(['name' => 'Other Store Tote']);

        $this->get(route('catalog.store', $storefrontProduct->store->slug))
            ->assertSee($storefrontProduct->name)
            ->assertDontSee($otherProduct->name)
            ->assertSee($storefrontProduct->store->name)
            ->assertSee('Open')
            ->assertSee('1 product')
            ->assertDontSee('from 2 sellers')
            ->assertDontSee($storefrontProduct->store->phone);
    }

    public function test_seller_storefront_only_lists_its_public_product_categories(): void
    {
        $firstCategory = Category::create(['name' => 'Desk goods', 'slug' => 'desk-goods', 'is_active' => true]);
        $secondCategory = Category::create(['name' => 'Travel goods', 'slug' => 'travel-goods', 'is_active' => true]);
        $otherCategory = Category::create(['name' => 'Other goods', 'slug' => 'other-goods', 'is_active' => true]);
        $firstProduct = $this->product(['category_id' => $firstCategory->id]);
        $this->product(['tenant_id' => $firstProduct->tenant_id, 'store_id' => $firstProduct->store_id, 'category_id' => $secondCategory->id]);
        $this->product(['category_id' => $otherCategory->id]);

        $this->get(route('catalog.store', $firstProduct->store->slug))
            ->assertSee('Desk goods')
            ->assertSee('Travel goods')
            ->assertDontSee('Other goods')
            ->assertDontSee('All sellers');
    }

    public function test_seller_sees_the_preview_toolbar_only_on_their_own_storefront(): void
    {
        $product = $this->product();
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        $otherSeller = User::factory()->seller()->create(['email_verified_at' => now()]);
        $otherTenant = Tenant::create(['name' => 'Other tenant', 'slug' => 'other-tenant-'.str()->lower(str()->random(8)), 'status' => 'active']);
        $otherStore = Store::create(['tenant_id' => $otherTenant->id, 'name' => 'Other Store', 'slug' => 'other-store-'.str()->lower(str()->random(8)), 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        TenantMember::create(['tenant_id' => $product->tenant_id, 'user_id' => $seller->id, 'role' => 'owner']);
        TenantMember::create(['tenant_id' => $otherTenant->id, 'user_id' => $otherSeller->id, 'role' => 'owner']);

        $this->actingAs($seller)->get(route('catalog.store', $product->store->slug))
            ->assertOk()
            ->assertSee('Storefront preview')
            ->assertDontSee(route('seller.dashboard'), false)
            ->assertDontSee(route('seller.products.index'), false);

        $this->actingAs($otherSeller)->get(route('catalog.store', $product->store->slug))
            ->assertOk()
            ->assertDontSee('Storefront preview')
            ->assertDontSee($otherStore->phone);
    }

    public function test_active_storefront_is_available_before_its_first_product_is_published(): void
    {
        $suffix = str()->lower(str()->random(8));
        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => 'New Store', 'slug' => "new-store-{$suffix}", 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);

        $this->get(route('catalog.store', $store->slug))
            ->assertOk()
            ->assertSee('New Store');
    }

    private function product(array $attributes = [], string $tenantStatus = 'active', string $storeStatus = 'active'): Product
    {
        $suffix = str()->lower(str()->random(8));
        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => $tenantStatus]);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => "Store {$suffix}", 'slug' => "store-{$suffix}", 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => $storeStatus]);

        return Product::create([
            'tenant_id' => $tenant->id, 'store_id' => $store->id, 'name' => "Product {$suffix}", 'slug' => "product-{$suffix}", 'sku' => "SKU-{$suffix}", 'price' => 199, 'stock_quantity' => 3, 'status' => 'active', ...$attributes,
        ]);
    }

    private function attachReview(Product $product, bool $withPhoto, string $status = 'published'): void
    {
        $buyer = User::factory()->create();
        $suffix = str()->lower(str()->random(8));
        $order = Order::create(['user_id' => $buyer->id, 'number' => "ORD-{$suffix}", 'status' => 'delivered', 'recipient_name' => 'Buyer', 'phone' => '+639171234567', 'address_line' => '1 Market Street', 'city' => 'Manila', 'subtotal' => 199, 'shipping_amount' => 0, 'total' => 199]);
        $sellerOrder = $order->sellerOrders()->create(['tenant_id' => $product->tenant_id, 'store_id' => $product->store_id, 'status' => 'delivered', 'subtotal' => 199, 'shipping_amount' => 0, 'total' => 199]);
        $item = $sellerOrder->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku, 'unit_price' => 199, 'quantity' => 1, 'line_total' => 199]);
        $review = ProductReview::create(['product_id' => $product->id, 'order_item_id' => $item->id, 'user_id' => $buyer->id, 'rating' => 5, 'status' => $status]);

        if ($withPhoto) {
            $review->photos()->create(['path' => 'reviews/photo.jpg']);
        }
    }
}
