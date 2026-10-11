<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\DeliveryAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_cart_redirects_to_cart_instead_of_rendering_checkout(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($customer)
            ->get(route('checkout.create'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('status', 'Your cart is empty. Add a product before checking out.');
    }

    public function test_buyer_cannot_view_another_buyers_order(): void
    {
        [, $product] = $this->sellerProduct('19.99', 5);
        $buyer = User::factory()->create();
        $otherBuyer = User::factory()->create();
        $this->actingAs($buyer)->post(route('cart.store', $product), ['quantity' => 1]);
        $this->actingAs($buyer)->post(route('checkout.store'), $this->delivery());
        $order = Order::query()->sole();

        $this->actingAs($otherBuyer)->get(route('orders.show', $order))->assertNotFound();
        $this->actingAs($buyer)->get(route('orders.show', $order))->assertOk();
    }

    public function test_guest_product_addition_is_applied_after_clerk_sign_in(): void
    {
        [, $product] = $this->sellerProduct('19.99', 5);
        $productPath = route('catalog.show', [$product->store->slug, $product->slug]);
        $loginUrl = route('login', ['redirect' => parse_url($productPath, PHP_URL_PATH), 'add_to_cart' => $product->id]);

        $this->get($productPath)
            ->assertSee('Sign in to add to cart')
            ->assertSee($loginUrl);

        $this->get(route('home'))
            ->assertSee(route('login', ['redirect' => '/', 'add_to_cart' => $product->id]));

        $this->get($loginUrl)
            ->assertSee('After signing in, this item will be added to your cart.');

        $buyer = User::factory()->create();
        $this->actingAs($buyer)
            ->postJson(route('clerk.handoff.complete'), ['redirect' => parse_url($productPath, PHP_URL_PATH)])
            ->assertOk()
            ->assertJsonPath('redirect', '/cart');

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->get(route('cart.index'))->assertSee('Added to your cart.');
    }

    public function test_admin_sign_in_does_not_apply_a_pending_product_addition(): void
    {
        [, $product] = $this->sellerProduct('19.99', 5);
        $productPath = route('catalog.show', [$product->store->slug, $product->slug]);
        $loginUrl = route('login', ['redirect' => parse_url($productPath, PHP_URL_PATH), 'add_to_cart' => $product->id]);

        $this->get($loginUrl)->assertOk();

        $administrator = User::factory()->administrator()->create();
        $this->actingAs($administrator)
            ->postJson(route('clerk.handoff.complete'), ['redirect' => parse_url($productPath, PHP_URL_PATH)])
            ->assertOk();

        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    public function test_sign_in_finishes_without_adding_an_item_that_sold_out_during_authentication(): void
    {
        [, $product] = $this->sellerProduct('19.99', 1);
        $productPath = route('catalog.show', [$product->store->slug, $product->slug]);
        $loginUrl = route('login', ['redirect' => parse_url($productPath, PHP_URL_PATH), 'add_to_cart' => $product->id]);

        $this->get($loginUrl)->assertOk();
        $product->update(['stock_quantity' => 0]);

        $buyer = User::factory()->create();
        $this->actingAs($buyer)
            ->postJson(route('clerk.handoff.complete'), ['redirect' => parse_url($productPath, PHP_URL_PATH)])
            ->assertOk()
            ->assertJsonPath('redirect', parse_url($productPath, PHP_URL_PATH));

        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    public function test_guest_cart_and_checkout_requests_return_to_the_requested_page_after_sign_in(): void
    {
        $this->get(route('cart.index'))
            ->assertRedirect(route('clerk.refresh', ['redirect' => '/cart']));

        $this->get(route('checkout.create'))
            ->assertRedirect(route('clerk.refresh', ['redirect' => '/checkout']));

        $this->get(route('clerk.refresh', ['redirect' => '/checkout']))
            ->assertOk()
            ->assertSee('data-redirect-url="/checkout"', false)
            ->assertSee('data-sign-in-url="'.route('login', ['redirect' => '/checkout']).'"', false);
    }

    public function test_checkout_renders_shipping_and_review_without_duplicate_mobile_action(): void
    {
        [, $product] = $this->sellerProduct('19.99', 5);
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $cart = Cart::create(['user_id' => $customer->id]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($customer)->get(route('checkout.create'))
            ->assertOk()
            ->assertSee('Review order')
            ->assertSee('Edit address')
            ->assertSee('Standard, free')
            ->assertSee('Cash on delivery.')
            ->assertDontSee('Continue to delivery')
            ->assertDontSee('Continue to payment');
    }

    public function test_checkout_uses_exact_totals_and_creates_order_snapshots(): void
    {
        [$seller, $product] = $this->sellerProduct('19.99', 5);
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $cart = Cart::create(['user_id' => $customer->id]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 3]);

        $response = $this->actingAs($customer)->post(route('checkout.store'), $this->delivery());
        $order = Order::query()->firstOrFail();

        $response->assertRedirect(route('orders.show', $order));
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'subtotal' => '59.97', 'total' => '59.97']);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => '19.99',
            'quantity' => 3,
            'line_total' => '59.97',
        ]);
        $this->assertSame(2, $product->refresh()->stock_quantity);
        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    public function test_customer_selects_a_color_and_size_and_cancellation_restores_that_stock(): void
    {
        [$seller, $product] = $this->sellerProduct('19.99', 5);
        $navy = $product->variants()->create(['color' => 'Navy', 'size' => 'M', 'sku' => 'TOTE-N-M', 'stock_quantity' => 3]);
        $red = $product->variants()->create(['color' => 'Red', 'size' => 'L', 'sku' => 'TOTE-R-L', 'stock_quantity' => 2]);
        [, $otherProduct] = $this->sellerProduct('12.00', 1);
        $otherVariant = $otherProduct->variants()->create(['color' => 'Black', 'size' => 'S', 'sku' => 'OTHER-B-S', 'stock_quantity' => 1]);
        $customer = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($customer)->get(route('catalog.show', [$product->store->slug, $product->slug]))
            ->assertOk()
            ->assertSee('Choose color and size')
            ->assertSee($navy->id);

        $this->actingAs($customer)->post(route('cart.store', $product), ['quantity' => 1])
            ->assertSessionHasErrors('variant_id');
        $this->actingAs($customer)->post(route('cart.store', $product), ['quantity' => 1, 'variant_id' => $otherVariant->id])
            ->assertSessionHasErrors('variant_id');
        $this->actingAs($customer)->post(route('cart.store', $product), ['quantity' => 4, 'variant_id' => $navy->id])
            ->assertSessionHasErrors('quantity');
        $this->actingAs($customer)->post(route('cart.store', $product), ['quantity' => 2, 'variant_id' => $navy->id])
            ->assertRedirect(route('cart.index'));
        $this->actingAs($customer)->post(route('cart.store', $product), ['quantity' => 1, 'variant_id' => $red->id])
            ->assertRedirect(route('cart.index'));
        $this->assertDatabaseCount('cart_items', 2);
        $this->actingAs($customer)->get(route('cart.index'))->assertOk()->assertSee('Navy / M')->assertSee('Red / L');

        $this->actingAs($customer)->post(route('checkout.store'), $this->delivery())->assertRedirect();
        $this->assertDatabaseHas('orders', ['subtotal' => '59.97']);
        $this->assertDatabaseHas('order_items', ['product_variant_id' => $navy->id, 'variant_label' => 'Navy / M', 'sku' => 'TOTE-N-M', 'quantity' => 2]);
        $this->actingAs($customer)->get(route('orders.show', Order::query()->sole()))
            ->assertOk()
            ->assertSee('Navy / M');
        $this->assertSame(1, $navy->refresh()->stock_quantity);
        $this->assertSame(1, $red->refresh()->stock_quantity);
        $this->assertSame(2, $product->refresh()->stock_quantity);

        $sellerOrder = SellerOrder::query()->sole();
        $this->actingAs($seller)->patch(route('seller.orders.update', $sellerOrder), ['status' => 'cancelled'])->assertRedirect();
        $this->assertSame(3, $navy->refresh()->stock_quantity);
        $this->assertSame(2, $red->refresh()->stock_quantity);
        $this->assertSame(5, $product->refresh()->stock_quantity);
    }

    public function test_seller_can_buy_from_another_store_with_the_same_account(): void
    {
        [$seller] = $this->sellerProduct('12.00', 5);
        [, $otherProduct] = $this->sellerProduct('19.99', 5);

        $this->actingAs($seller)->post(route('cart.store', $otherProduct), ['quantity' => 2])
            ->assertRedirect(route('cart.index'));
        $this->actingAs($seller)->post(route('checkout.store'), $this->delivery())
            ->assertRedirect();

        $order = Order::query()->sole();
        $this->assertSame($seller->id, $order->user_id);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'total' => '39.98']);
        $this->actingAs($seller)->get(route('orders.index'))->assertOk()->assertSee($order->number);
        $this->actingAs($seller)->get(route('seller.orders.index'))->assertOk()->assertDontSee($order->number);
        $this->assertDatabaseCount('seller_earnings', 0);
    }

    public function test_seller_checkout_offers_only_their_saved_delivery_addresses(): void
    {
        [$seller] = $this->sellerProduct('12.00', 5);
        [, $otherProduct] = $this->sellerProduct('19.99', 5);
        $otherUser = User::factory()->create();
        DeliveryAddress::factory()->for($seller, 'user')->create(['address_line' => 'My saved street']);
        DeliveryAddress::factory()->for($otherUser, 'user')->create(['address_line' => 'Private street']);
        $cart = Cart::create(['user_id' => $seller->id]);
        $cart->items()->create(['product_id' => $otherProduct->id, 'quantity' => 1]);

        $this->actingAs($seller)->get(route('checkout.create'))
            ->assertOk()->assertSee('My saved street')->assertDontSee('Private street');
    }

    public function test_seller_cannot_add_a_product_from_a_store_they_manage(): void
    {
        [$seller, $product] = $this->sellerProduct('19.99', 5);
        $seller->update(['preferred_workspace' => 'customer']);

        $this->actingAs($seller)->get(route('home'))
            ->assertOk()
            ->assertSee('You cannot buy from your store');
        $this->actingAs($seller)->get(route('catalog.show', [$product->store->slug, $product->slug]))
            ->assertOk()
            ->assertSee('You cannot buy products from a store you manage.');

        $this->actingAs($seller)->post(route('cart.store', $product), ['quantity' => 1])
            ->assertSessionHasErrors(['cart' => 'You cannot buy products from a store you manage.']);

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_mixed_cart_with_a_managed_store_product_is_rejected_atomically(): void
    {
        [$seller, $ownProduct] = $this->sellerProduct('12.00', 5);
        [, $otherProduct] = $this->sellerProduct('19.99', 5);
        $cart = Cart::create(['user_id' => $seller->id]);
        $cart->items()->create(['product_id' => $ownProduct->id, 'quantity' => 1]);
        $cart->items()->create(['product_id' => $otherProduct->id, 'quantity' => 1]);

        $this->actingAs($seller)->post(route('checkout.store'), $this->delivery())
            ->assertSessionHasErrors(['cart' => 'Remove products from stores you manage before checking out.']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 2);
        $this->assertSame(5, $ownProduct->refresh()->stock_quantity);
        $this->assertSame(5, $otherProduct->refresh()->stock_quantity);
    }

    public function test_checkout_rejects_totals_that_exceed_order_storage_precision(): void
    {
        [, $product] = $this->sellerProduct('9000000000.00', 5);
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $cart = Cart::create(['user_id' => $customer->id]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 2]);

        $this->actingAs($customer)
            ->post(route('checkout.store'), $this->delivery())
            ->assertSessionHasErrors(['cart' => 'Your cart total exceeds the maximum order amount.']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2]);
        $this->assertSame(5, $product->refresh()->stock_quantity);
    }

    public function test_cancelling_a_confirmed_seller_order_restores_stock_and_updates_parent_order(): void
    {
        [$seller, $product] = $this->sellerProduct('20.00', 5);
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $cart = Cart::create(['user_id' => $customer->id]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 2]);
        $this->actingAs($customer)->post(route('checkout.store'), $this->delivery());
        $sellerOrder = SellerOrder::query()->firstOrFail();

        $this->actingAs($seller)
            ->patch(route('seller.orders.update', $sellerOrder), ['status' => 'confirmed'])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', ['id' => $sellerOrder->order_id, 'status' => 'confirmed']);

        $this->actingAs($seller)
            ->patch(route('seller.orders.update', $sellerOrder), ['status' => 'cancelled'])
            ->assertRedirect();

        $this->assertDatabaseHas('seller_orders', ['id' => $sellerOrder->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('orders', ['id' => $sellerOrder->order_id, 'status' => 'cancelled']);
        $this->assertSame(5, $product->refresh()->stock_quantity);
    }

    public function test_seller_cannot_update_another_tenants_order(): void
    {
        [$seller, $product] = $this->sellerProduct('20.00', 5);
        [, $otherProduct] = $this->sellerProduct('30.00', 5);
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $cart = Cart::create(['user_id' => $customer->id]);
        $cart->items()->create(['product_id' => $otherProduct->id, 'quantity' => 1]);

        $this->actingAs($customer)->post(route('checkout.store'), $this->delivery());
        $sellerOrder = SellerOrder::query()->firstOrFail();

        $this->actingAs($seller)
            ->patch(route('seller.orders.update', $sellerOrder), ['status' => 'confirmed'])
            ->assertNotFound();

        $this->assertDatabaseHas('seller_orders', ['id' => $sellerOrder->id, 'status' => 'pending']);
        $this->assertSame(5, $product->refresh()->stock_quantity);
    }

    public function test_cancelling_one_seller_order_reprices_a_multi_store_order_for_the_remaining_delivery(): void
    {
        [$firstSeller, $firstProduct] = $this->sellerProduct('20.00', 5);
        [$secondSeller, $secondProduct] = $this->sellerProduct('30.00', 5);
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $cart = Cart::create(['user_id' => $customer->id]);
        $cart->items()->create(['product_id' => $firstProduct->id, 'quantity' => 1]);
        $cart->items()->create(['product_id' => $secondProduct->id, 'quantity' => 1]);

        $this->actingAs($customer)->post(route('checkout.store'), $this->delivery());
        $order = Order::query()->sole();
        $firstSellerOrder = $order->sellerOrders()->where('store_id', $firstProduct->store_id)->sole();
        $secondSellerOrder = $order->sellerOrders()->where('store_id', $secondProduct->store_id)->sole();

        $this->actingAs($firstSeller)->patch(route('seller.orders.update', $firstSellerOrder), ['status' => 'confirmed']);
        $this->actingAs($firstSeller)->patch(route('seller.orders.update', $firstSellerOrder), ['status' => 'cancelled']);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'subtotal' => '30.00', 'total' => '30.00']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'amount' => '30.00']);
        $this->assertSame(5, $firstProduct->refresh()->stock_quantity);

        $this->actingAs($secondSeller)->patch(route('seller.orders.update', $secondSellerOrder), ['status' => 'confirmed']);
        $this->actingAs($secondSeller)->patch(route('seller.orders.update', $secondSellerOrder), ['status' => 'processing']);
        $this->actingAs($secondSeller)->patch(route('seller.orders.update', $secondSellerOrder), ['status' => 'shipped']);
        $this->actingAs($secondSeller)->patch(route('seller.orders.update', $secondSellerOrder), ['status' => 'delivered']);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered', 'total' => '30.00']);
    }

    public function test_customer_can_search_purchases_by_product_or_store_name(): void
    {
        [, $product] = $this->sellerProduct('20.00', 5);
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $cart = Cart::create(['user_id' => $customer->id]);
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($customer)->post(route('checkout.store'), $this->delivery());

        $this->actingAs($customer)
            ->get(route('orders.index', ['search' => $product->name]))
            ->assertOk()
            ->assertSee($product->name);

        $this->actingAs($customer)
            ->get(route('orders.index', ['search' => $product->store->name]))
            ->assertOk()
            ->assertSee($product->store->name);
    }

    /**
     * @return array{0: User, 1: Product}
     */
    private function sellerProduct(string $price, int $stock): array
    {
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        $suffix = str()->lower(str()->random(6));
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
            'name' => 'Canvas Tote',
            'slug' => "canvas-tote-{$suffix}",
            'sku' => "TOTE-{$suffix}",
            'price' => $price,
            'stock_quantity' => $stock,
            'status' => 'active',
        ]);

        return [$seller, $product];
    }

    /**
     * @return array{recipient_name: string, phone: string, address_line: string, city: string}
     */
    private function delivery(): array
    {
        return [
            'recipient_name' => 'Ava Buyer',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
        ];
    }
}
