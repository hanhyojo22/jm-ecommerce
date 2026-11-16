<?php

namespace Tests\Feature;

use App\Actions\UpdateSellerOrderStatus;
use App\Models\BranchStock;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BranchCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'api.paymongo.com/v1/checkout_sessions' => Http::response(['data' => ['id' => 'cs_1', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_1']]]),
            '*' => Http::response(),
        ]);
    }

    public function test_an_order_takes_stock_from_the_chosen_branch_and_a_cancel_puts_it_back_there(): void
    {
        [$makati, $cebu, $product] = $this->pharmacyWithStock(makati: 5, cebu: 3);
        $buyer = $this->buyer();

        $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['branch' => $cebu->id, 'product_id' => $product->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('data.branch.id', $cebu->id);
        $this->postJson('/api/v1/checkout', [...$this->delivery(), 'payment_method' => 'cod'])->assertCreated();

        $sellerOrder = SellerOrder::query()->sole();
        $this->assertSame($cebu->id, $sellerOrder->store_id);
        $this->assertSame([5, 1], [$this->onHand($makati, $product), $this->onHand($cebu, $product)]);
        $this->assertSame(6, $product->fresh()->stock_quantity);

        app(UpdateSellerOrderStatus::class)->handle($sellerOrder, 'cancelled', null);

        $this->assertSame([5, 3], [$this->onHand($makati, $product), $this->onHand($cebu, $product)]);
        $this->assertSame(8, $product->fresh()->stock_quantity);
    }

    public function test_a_branch_cannot_sell_more_than_it_has_even_when_another_branch_has_plenty(): void
    {
        [, $cebu, $product] = $this->pharmacyWithStock(makati: 50, cebu: 1);

        $this->actingAs($this->buyer())->postJson('/api/v1/cart/items', ['branch' => $cebu->id, 'product_id' => $product->id, 'quantity' => 2])
            ->assertUnprocessable();
    }

    public function test_closing_the_first_branch_does_not_stop_buying_at_another(): void
    {
        [$makati, $cebu, $product] = $this->pharmacyWithStock(makati: 5, cebu: 4);
        $makati->update(['status' => 'inactive']);
        $buyer = $this->buyer();

        $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['branch' => $cebu->id, 'product_id' => $product->id, 'quantity' => 1])->assertCreated();
        $this->postJson('/api/v1/checkout', [...$this->delivery(), 'payment_method' => 'cod'])->assertCreated();

        $this->assertSame($cebu->id, SellerOrder::query()->sole()->store_id);
    }

    public function test_a_pinned_delivery_needs_no_province_or_postal_code(): void
    {
        [, $cebu, $product] = $this->pharmacyWithStock(makati: 0, cebu: 3);
        $buyer = $this->buyer();

        $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['branch' => $cebu->id, 'product_id' => $product->id, 'quantity' => 1])->assertCreated();
        $this->postJson('/api/v1/checkout', [...collect($this->delivery())->except(['province', 'postal_code'])->all(), 'payment_method' => 'cod'])
            ->assertCreated();

        $this->assertDatabaseHas('orders', ['user_id' => $buyer->id, 'province' => null, 'postal_code' => null]);
    }

    public function test_the_app_must_choose_a_branch_before_its_first_item(): void
    {
        [, , $product] = $this->pharmacyWithStock(makati: 5, cebu: 5);

        $this->actingAs($this->buyer())->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('error.fields.branch.0', 'The branch field is required.');
    }

    public function test_checkout_is_refused_once_the_carts_branch_closes(): void
    {
        [, $cebu, $product] = $this->pharmacyWithStock(makati: 5, cebu: 5);
        $buyer = $this->buyer();
        $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['branch' => $cebu->id, 'product_id' => $product->id, 'quantity' => 1])->assertCreated();
        $cebu->update(['status' => 'inactive']);

        $this->postJson('/api/v1/checkout', [...$this->delivery(), 'payment_method' => 'cod'])->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $this->onHand($cebu, $product));
    }

    public function test_delivery_adds_the_flat_fee_unless_the_items_reach_the_free_delivery_amount(): void
    {
        [, $cebu, $product] = $this->pharmacyWithStock(makati: 0, cebu: 20);
        $cebu->tenant->update(['delivery_fee' => '49.00', 'free_delivery_minimum' => '50.00']);
        $buyer = $this->buyer();

        $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['branch' => $cebu->id, 'product_id' => $product->id, 'quantity' => 2])->assertCreated();
        $this->getJson('/api/v1/checkout')
            ->assertOk()
            ->assertJsonPath('data.branch.id', $cebu->id)
            ->assertJsonPath('data.fulfillment_methods.delivery.fee', '49.00')
            ->assertJsonPath('data.fulfillment_methods.delivery.total', '62.00')
            ->assertJsonPath('data.fulfillment_methods.pickup.total', '13.00');
        $this->postJson('/api/v1/checkout', [...$this->delivery(), 'payment_method' => 'cod'])
            ->assertCreated()
            ->assertJsonPath('data.fulfillment_method', 'delivery')
            ->assertJsonPath('data.delivery_pin.latitude', 10.3157);
        $this->assertDatabaseHas('orders', ['subtotal' => '13.00', 'shipping_amount' => '49.00', 'total' => '62.00']);
        $this->assertDatabaseHas('payments', ['amount' => '62.00']);

        // Eight tablets at ₱6.50 reach the ₱50 free-delivery amount.
        $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 8])->assertCreated();
        $this->postJson('/api/v1/checkout', [...$this->delivery(), 'payment_method' => 'cod'])->assertCreated();
        $this->assertDatabaseHas('orders', ['subtotal' => '52.00', 'shipping_amount' => '0.00', 'total' => '52.00']);
    }

    public function test_an_online_payment_charges_the_delivery_fee_as_its_own_line(): void
    {
        config()->set('services.paymongo', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsk_test', 'base_url' => 'https://api.paymongo.com']);
        [, $cebu, $product] = $this->pharmacyWithStock(makati: 0, cebu: 5);
        $cebu->tenant->update(['delivery_fee' => '49.00']);
        $buyer = $this->buyer();

        $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['branch' => $cebu->id, 'product_id' => $product->id, 'quantity' => 2])->assertCreated();
        $this->postJson('/api/v1/checkout', [...$this->delivery(), 'payment_method' => 'gcash'])
            ->assertCreated()
            ->assertJsonPath('data.payment_url', 'https://checkout.paymongo.com/cs_1');

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/checkout_sessions')
            && collect($request['data']['attributes']['line_items'])->contains(fn (array $line): bool => $line['name'] === 'Delivery fee' && $line['amount'] === 4900)
            && collect($request['data']['attributes']['line_items'])->reject(fn (array $line): bool => $line['name'] === 'Payment fee')->sum(fn (array $line): int => $line['amount'] * $line['quantity']) === 6200);
    }

    public function test_the_quoted_online_fee_is_the_fee_charged_for_a_delivery(): void
    {
        config()->set('services.paymongo', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsk_test', 'base_url' => 'https://api.paymongo.com']);
        [, $cebu, $product] = $this->pharmacyWithStock(makati: 0, cebu: 5);
        $cebu->tenant->update(['delivery_fee' => '49.00']);
        $buyer = $this->buyer();
        $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['branch' => $cebu->id, 'product_id' => $product->id, 'quantity' => 2])->assertCreated();

        $quote = $this->getJson('/api/v1/checkout')->assertOk();
        $this->assertSame($quote->json('data.payment_methods.gcash.fee'), $quote->json('data.fulfillment_methods.delivery.payment_fees.gcash'));
        $this->assertNotSame($quote->json('data.fulfillment_methods.delivery.payment_fees.gcash'), $quote->json('data.fulfillment_methods.pickup.payment_fees.gcash'));

        $this->postJson('/api/v1/checkout', [...$this->delivery(), 'payment_method' => 'gcash'])->assertCreated();
        $this->assertSame($quote->json('data.payment_methods.gcash.fee'), (string) Payment::query()->sole()->fee_amount);
    }

    public function test_the_web_checkout_shows_the_delivery_fee_it_charges(): void
    {
        [$makati, , $product] = $this->pharmacyWithStock(makati: 5, cebu: 0);
        $makati->tenant->update(['delivery_fee' => '49.00']);
        $buyer = $this->buyer();
        $this->actingAs($buyer)->post(route('cart.store', $product), ['quantity' => 2]);

        $this->get(route('checkout.create'))
            ->assertOk()
            ->assertSee('Delivery fee')
            ->assertSee('₱49.00')
            ->assertSee('₱62.00');

        $this->post(route('checkout.store'), collect($this->delivery())->except(['delivery_latitude', 'delivery_longitude'])->all())->assertRedirect();
        $this->assertDatabaseHas('orders', ['shipping_amount' => '49.00', 'total' => '62.00']);
    }

    public function test_the_web_product_page_and_checkout_show_the_carts_branch_and_its_stock(): void
    {
        [, $cebu, $product] = $this->pharmacyWithStock(makati: 5, cebu: 3);
        $this->actingAs($this->buyer())->postJson('/api/v1/cart/items', ['branch' => $cebu->id, 'product_id' => $product->id, 'quantity' => 1])->assertCreated();

        $this->get(route('catalog.show', ['botika-plus-makati', $product->slug]))
            ->assertOk()
            ->assertSeeInOrder(['Sold by', 'Botika Plus Cebu'])
            ->assertSee('In stock (3 available)')
            ->assertDontSee('<b>Botika Plus Makati</b>', false);
        $this->get(route('checkout.create'))
            ->assertOk()
            ->assertSee('Botika Plus Cebu')
            ->assertDontSee('Botika Plus Makati');
    }

    public function test_switching_branch_keeps_the_items_and_unticks_what_the_new_branch_lacks(): void
    {
        [$makati, $cebu, $product] = $this->pharmacyWithStock(makati: 0, cebu: 5);
        $buyer = $this->buyer();
        $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['branch' => $cebu->id, 'product_id' => $product->id, 'quantity' => 2])->assertCreated();
        // Lines are listed under the branch the cart buys from, not the product's first branch.
        $this->getJson('/api/v1/cart')->assertOk()->assertJsonPath('data.items.0.product.store.name', $cebu->name);

        $this->patchJson('/api/v1/cart/branch', ['branch' => $makati->id])
            ->assertOk()
            ->assertJsonPath('data.branch.id', $makati->id)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.available', false)
            ->assertJsonPath('data.items.0.selected', false);

        $makati->update(['status' => 'inactive']);
        $this->patchJson('/api/v1/cart/branch', ['branch' => $makati->id])->assertNotFound();
    }

    public function test_a_web_cart_left_on_a_closed_branch_moves_to_an_open_one(): void
    {
        [$makati, $cebu, $product] = $this->pharmacyWithStock(makati: 5, cebu: 5);
        $buyer = $this->buyer();
        $this->actingAs($buyer)->post(route('cart.store', $product), ['quantity' => 1])->assertSessionHasNoErrors();
        $this->assertSame($makati->id, $buyer->cart()->value('store_id'));

        $makati->update(['status' => 'inactive']);
        $this->post(route('cart.store', $product), ['quantity' => 1])->assertSessionHasNoErrors();
        $this->post(route('checkout.store'), collect($this->delivery())->except(['delivery_latitude', 'delivery_longitude'])->all())->assertRedirect();

        $this->assertSame($cebu->id, SellerOrder::query()->sole()->store_id);
    }

    public function test_pickup_is_free_needs_no_address_and_names_the_branch(): void
    {
        [, $cebu, $product] = $this->pharmacyWithStock(makati: 0, cebu: 5);
        $cebu->tenant->update(['delivery_fee' => '49.00']);
        $buyer = $this->buyer();

        $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['branch' => $cebu->id, 'product_id' => $product->id, 'quantity' => 1])->assertCreated();
        $this->postJson('/api/v1/checkout', ['fulfillment_method' => 'pickup', 'recipient_name' => 'Ava Buyer', 'phone' => '+639171234567', 'payment_method' => 'cod'])
            ->assertCreated()
            ->assertJsonPath('data.fulfillment_method', 'pickup')
            ->assertJsonPath('data.delivery_pin', null);

        $this->assertDatabaseHas('orders', ['fulfillment_method' => 'pickup', 'address_line' => '10 Rizal Street', 'city' => 'Cebu', 'shipping_amount' => '0.00', 'total' => '6.50']);
        $this->assertDatabaseCount('delivery_addresses', 0);
    }

    public function test_delivery_outside_the_branch_radius_is_refused_and_the_app_must_send_a_pin(): void
    {
        [, $cebu, $product] = $this->pharmacyWithStock(makati: 0, cebu: 5);
        $cebu->update(['latitude' => 10.3157, 'longitude' => 123.8854, 'delivery_radius_km' => 5]);
        $buyer = $this->buyer();
        $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['branch' => $cebu->id, 'product_id' => $product->id, 'quantity' => 1])->assertCreated();

        // Lapu-Lapu is about 13 km away.
        $this->postJson('/api/v1/checkout', [...$this->delivery(), 'delivery_latitude' => 10.3103, 'delivery_longitude' => 124.0170, 'payment_method' => 'cod'])
            ->assertUnprocessable()
            ->assertJsonPath('error.fields.delivery_latitude.0', fn (string $message): bool => str_contains($message, 'delivers within 5 km'));
        $this->postJson('/api/v1/checkout', [...collect($this->delivery())->except(['delivery_latitude', 'delivery_longitude'])->all(), 'payment_method' => 'cod'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['delivery_latitude'], 'error.fields');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $this->onHand($cebu, $product));

        $this->postJson('/api/v1/checkout', [...$this->delivery(), 'payment_method' => 'cod'])->assertCreated();
    }

    public function test_a_retry_with_a_different_fulfillment_is_not_mistaken_for_the_first_order(): void
    {
        [, $cebu, $product] = $this->pharmacyWithStock(makati: 0, cebu: 5);
        $buyer = $this->buyer();
        $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['branch' => $cebu->id, 'product_id' => $product->id, 'quantity' => 1])->assertCreated();
        $this->postJson('/api/v1/checkout', [...$this->delivery(), 'payment_method' => 'cod', 'client_request_id' => 'retry-1'])->assertCreated();

        $this->postJson('/api/v1/checkout', [...$this->delivery(), 'fulfillment_method' => 'pickup', 'payment_method' => 'cod', 'client_request_id' => 'retry-1'])
            ->assertUnprocessable();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_pickup_orders_are_never_marked_collected_automatically(): void
    {
        [, $cebu, $product] = $this->pharmacyWithStock(makati: 0, cebu: 5);
        $buyer = $this->buyer();
        $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['branch' => $cebu->id, 'product_id' => $product->id, 'quantity' => 1])->assertCreated();
        $this->postJson('/api/v1/checkout', ['fulfillment_method' => 'pickup', 'recipient_name' => 'Ava Buyer', 'phone' => '+639171234567', 'payment_method' => 'cod'])->assertCreated();
        SellerOrder::query()->update(['status' => 'shipped', 'shipped_at' => now()->subDays(30)]);

        $this->artisan('orders:auto-deliver')->assertSuccessful();

        $this->assertSame('shipped', SellerOrder::query()->sole()->status);
    }

    public function test_a_closed_branch_refuses_delivery_but_takes_pickup(): void
    {
        [, $cebu, $product] = $this->pharmacyWithStock(makati: 0, cebu: 5);
        $cebu->update(['opening_time' => '08:00', 'closing_time' => '20:00', 'latitude' => 10.3157, 'longitude' => 123.8854]);
        $cebu->tenant->stores()->whereKeyNot($cebu->id)->update(['status' => 'inactive']);
        $this->travelTo(Carbon::parse('2026-11-16 21:30', 'Asia/Manila'));
        $buyer = $this->buyer();
        $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['branch' => $cebu->id, 'product_id' => $product->id, 'quantity' => 1])->assertCreated();

        $this->getJson('/api/v1/checkout')->assertOk()->assertJsonPath('data.fulfillment_methods.delivery.closed_until', '2026-11-17T08:00:00+08:00');
        $this->getJson(route('api.v1.store-locations.index'))
            ->assertJsonPath('data.0.is_open', false)
            ->assertJsonPath('data.0.next_opening_at', '2026-11-17T08:00:00+08:00');
        $this->postJson('/api/v1/checkout', [...$this->delivery(), 'payment_method' => 'cod'])
            ->assertUnprocessable()
            ->assertJsonPath('error.fields.cart.0', fn (string $message): bool => str_contains($message, 'opens at 8:00 AM'));
        $this->assertDatabaseCount('orders', 0);

        $this->postJson('/api/v1/checkout', ['fulfillment_method' => 'pickup', 'recipient_name' => 'Ava Buyer', 'phone' => '+639171234567', 'payment_method' => 'cod'])
            ->assertCreated();
    }

    public function test_a_branch_without_hours_always_takes_delivery(): void
    {
        [, $cebu, $product] = $this->pharmacyWithStock(makati: 0, cebu: 5);
        $this->travelTo(Carbon::parse('2026-11-16 03:00', 'Asia/Manila'));
        $this->actingAs($this->buyer())->postJson('/api/v1/cart/items', ['branch' => $cebu->id, 'product_id' => $product->id, 'quantity' => 1])->assertCreated();

        $this->postJson('/api/v1/checkout', [...$this->delivery(), 'payment_method' => 'cod'])->assertCreated();
    }

    public function test_order_again_refills_the_cart_and_names_what_the_branch_lacks(): void
    {
        [, $cebu, $paracetamol] = $this->pharmacyWithStock(makati: 0, cebu: 10);
        $cetirizine = Product::create([
            'tenant_id' => $cebu->tenant_id, 'store_id' => $cebu->id, 'name' => 'Cetirizine 10mg', 'slug' => 'cetirizine-10mg',
            'sku' => 'CET-10', 'price' => '12.00', 'stock_quantity' => 5, 'status' => 'active',
        ]);
        BranchStock::create(['store_id' => $cebu->id, 'product_id' => $cetirizine->id, 'variant_key' => 'default', 'quantity' => 5]);
        $buyer = $this->buyer();
        $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['branch' => $cebu->id, 'product_id' => $paracetamol->id, 'quantity' => 2])->assertCreated();
        $this->postJson('/api/v1/cart/items', ['product_id' => $cetirizine->id, 'quantity' => 1])->assertCreated();
        $order = $this->postJson('/api/v1/checkout', [...$this->delivery(), 'payment_method' => 'cod'])->assertCreated()->json('data.id');
        BranchStock::query()->where('product_id', $cetirizine->id)->update(['quantity' => 0]);

        $this->postJson(route('api.v1.cart.reorder', $order))
            ->assertOk()
            ->assertJsonPath('skipped', ['Cetirizine 10mg'])
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 2);

        $this->actingAs($this->buyer())->postJson(route('api.v1.cart.reorder', $order))->assertNotFound();
    }

    public function test_only_head_office_sets_the_delivery_fee(): void
    {
        [$makati] = $this->pharmacyWithStock(makati: 1, cebu: 1);
        $tenant = $makati->tenant;
        $headOffice = $this->member($tenant);
        $branchManager = $this->member($tenant, $makati);

        $this->actingAs($branchManager)->patch(route('seller.delivery-fee.update'), ['delivery_fee' => '1.00'])->assertForbidden();
        $this->actingAs($headOffice)->patch(route('seller.delivery-fee.update'), ['delivery_fee' => '49.00', 'free_delivery_minimum' => '500'])
            ->assertRedirect(route('seller.branches.index'));

        $this->assertSame(['49.00', '500.00'], [(string) $tenant->fresh()->delivery_fee, (string) $tenant->fresh()->free_delivery_minimum]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.delivery_fee_updated', 'actor_user_id' => $headOffice->id]);
    }

    private function member(Tenant $tenant, ?Store $branch = null): User
    {
        $user = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $tenant->id, 'store_id' => $branch?->id, 'user_id' => $user->id, 'role' => 'manager']);

        return $user;
    }

    /** @return array{0: Store, 1: Store, 2: Product} */
    private function pharmacyWithStock(int $makati, int $cebu): array
    {
        $tenant = Tenant::create(['name' => 'Botika Plus', 'slug' => 'botika-plus', 'status' => 'active']);
        $branch = fn (string $city): Store => Store::create([
            'tenant_id' => $tenant->id, 'name' => "Botika Plus {$city}", 'slug' => 'botika-plus-'.strtolower($city),
            'phone' => '+639171234567', 'address_line' => '10 Rizal Street', 'city' => $city, 'status' => 'active',
        ]);
        [$makatiBranch, $cebuBranch] = [$branch('Makati'), $branch('Cebu')];
        $product = Product::create([
            'tenant_id' => $tenant->id, 'store_id' => $makatiBranch->id, 'name' => 'Paracetamol 500mg', 'slug' => 'paracetamol-500mg',
            'sku' => 'PARA-500', 'price' => '6.50', 'stock_quantity' => $makati + $cebu, 'status' => 'active',
        ]);
        foreach ([[$makatiBranch, $makati], [$cebuBranch, $cebu]] as [$store, $quantity]) {
            BranchStock::create(['store_id' => $store->id, 'product_id' => $product->id, 'variant_key' => 'default', 'quantity' => $quantity]);
        }

        return [$makatiBranch, $cebuBranch, $product];
    }

    private function onHand(Store $branch, Product $product): int
    {
        return (int) BranchStock::query()->where('store_id', $branch->id)->where('product_id', $product->id)->value('quantity');
    }

    private function buyer(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /** @return array<string, string> */
    private function delivery(): array
    {
        return ['recipient_name' => 'Ava Buyer', 'phone' => '+639171234567', 'address_line' => '100 Colon Street', 'city' => 'Cebu', 'province' => 'Cebu', 'postal_code' => '6000', 'delivery_latitude' => 10.3157, 'delivery_longitude' => 123.8854];
    }
}
