<?php

namespace Tests\Feature;

use App\Actions\UpdateSellerOrderStatus;
use App\Models\BranchStock;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeniorPwdDiscountTest extends TestCase
{
    use RefreshDatabase;

    private Store $branch;

    private Product $paracetamol;

    private Product $shampoo;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('marketplace.senior_pwd.vat_registered', true);

        $tenant = Tenant::create(['name' => 'Botika Plus', 'slug' => 'botika-plus', 'status' => 'active', 'delivery_fee' => '49.00']);
        $this->branch = Store::create([
            'tenant_id' => $tenant->id, 'name' => 'Botika Plus Cebu', 'slug' => 'botika-plus-cebu',
            'phone' => '+639171234567', 'address_line' => '10 Rizal Street', 'city' => 'Cebu', 'status' => 'active',
        ]);
        $this->paracetamol = $this->product('Paracetamol 500mg', '7.00', 'Paracetamol');
        $this->shampoo = $this->product('Herbal Shampoo', '150.00', null);
    }

    public function test_the_card_takes_the_discount_off_medicines_only_and_every_total_agrees(): void
    {
        $buyer = $this->buyerWithCart();

        // 3 × ₱7.00 = ₱21.00 → ₱18.75 without VAT → less ₱3.75 → pays ₱15.00, so ₱6.00 off. Shampoo is not a medicine.
        $this->actingAs($buyer)->getJson('/api/v1/checkout')->assertOk()->assertJsonPath('data.senior_pwd_discount', '6.00');

        $orderId = $this->postJson('/api/v1/checkout', [...$this->delivery(), 'payment_method' => 'cod', 'discount_card' => ['type' => 'senior', 'id_number' => 'SC-2026-00123', 'name' => 'Lola Ava']])
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '171.00')
            ->assertJsonPath('data.discount_amount', '6.00')
            ->assertJsonPath('data.total', '214.00')
            ->assertJsonPath('data.discount_card.id_number', '•••••••••0123')
            ->json('data.id');

        $order = Order::with(['payment', 'sellerOrders.items'])->findOrFail($orderId);
        $sellerOrder = $order->sellerOrders->sole();
        $this->assertSame('214.00', (string) $order->payment->amount);
        $this->assertSame(['6.00', '214.00'], [(string) $sellerOrder->discount_amount, (string) $sellerOrder->total]);
        $this->assertSame(['0.00', '6.00'], $sellerOrder->items->sortBy('product_name')->pluck('discount_amount')->map(fn ($amount): string => (string) $amount)->values()->all());
        $this->assertNotSame('SC-2026-00123', DB::table('orders')->whereKey($orderId)->value('discount_card_number'));
        $this->assertSame('SC-2026-00123', $order->discount_card_number);

        $this->get(route('orders.show', $order))->assertSee('Senior citizen discount')->assertSee('−₱6.00', false)->assertDontSee('SC-2026-00123');
        $this->actingAs($this->staff())->get(route('seller.orders.show', $sellerOrder))->assertSee('SC-2026-00123')->assertSee('Lola Ava');

        app(UpdateSellerOrderStatus::class)->handle($sellerOrder, 'cancelled', null);
        $this->assertSame(['0.00', '0.00'], [(string) $order->refresh()->discount_amount, (string) $order->total]);
    }

    public function test_the_app_pickup_checkout_with_a_card_places_the_order(): void
    {
        $buyer = $this->buyerWithCart();

        $this->actingAs($buyer)->postJson('/api/v1/checkout', [
            'recipient_name' => 'Lola Tester', 'phone' => '09171234567', 'address_line' => '', 'city' => 'Cebu', 'province' => '', 'postal_code' => '',
            'fulfillment_method' => 'pickup', 'payment_method' => 'cod', 'client_request_id' => '6c1f3b9e-1111-4a2b-9c3d-123456789abc',
            'discount_card' => ['type' => 'senior', 'id_number' => 'SC-TEST-4821', 'name' => 'Lola Tester'],
        ])->assertCreated()->assertJsonPath('data.total', '165.00');
    }

    public function test_the_website_checkout_offers_the_card_and_applies_it(): void
    {
        $buyer = $this->buyerWithCart();

        $this->actingAs($buyer)->get(route('checkout.create'))->assertSee('Senior citizen / PWD discount')->assertSee('−₱6.00', false);
        $this->post(route('checkout.store'), [...$this->delivery(), 'discount_card' => ['type' => 'pwd', 'id_number' => 'PWD-778', 'name' => 'Ava Buyer']])
            ->assertSessionHasNoErrors();

        $this->assertSame(['pwd', '6.00', '214.00'], [Order::sole()->discount_card_type, (string) Order::sole()->discount_amount, (string) Order::sole()->total]);
    }

    public function test_the_card_needs_a_number_and_name_and_a_medicine_in_the_cart(): void
    {
        $buyer = $this->buyerWithCart();

        $this->actingAs($buyer)->postJson('/api/v1/checkout', [...$this->delivery(), 'discount_card' => ['type' => 'senior']])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['fields' => ['discount_card.id_number', 'discount_card.name']]]);

        $buyer->cart->items()->where('product_id', $this->paracetamol->id)->delete();
        $this->postJson('/api/v1/checkout', [...$this->delivery(), 'discount_card' => ['type' => 'senior', 'id_number' => 'SC-1', 'name' => 'Lola Ava']])
            ->assertUnprocessable()
            ->assertJsonPath('error.fields.discount_card.0', 'The senior citizen / PWD discount is for medicines, and none of these items are medicines.');
        $this->assertDatabaseCount('orders', 0);
    }

    private function product(string $name, string $price, ?string $generic): Product
    {
        $product = Product::create([
            'tenant_id' => $this->branch->tenant_id, 'store_id' => $this->branch->id, 'name' => $name, 'slug' => str($name)->slug()->toString(),
            'sku' => str($name)->slug()->upper()->toString(), 'price' => $price, 'stock_quantity' => 10, 'status' => 'active', 'generic_name' => $generic,
        ]);
        BranchStock::create(['store_id' => $this->branch->id, 'product_id' => $product->id, 'variant_key' => 'default', 'quantity' => 10]);

        return $product;
    }

    private function buyerWithCart(): User
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['branch' => $this->branch->id, 'product_id' => $this->paracetamol->id, 'quantity' => 3])->assertCreated();
        $this->postJson('/api/v1/cart/items', ['product_id' => $this->shampoo->id, 'quantity' => 1])->assertCreated();

        return $buyer->refresh();
    }

    private function staff(): User
    {
        $user = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $this->branch->tenant_id, 'store_id' => $this->branch->id, 'user_id' => $user->id, 'role' => 'staff']);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function delivery(): array
    {
        return ['recipient_name' => 'Ava Buyer', 'phone' => '+639171234567', 'address_line' => '100 Colon Street', 'city' => 'Cebu', 'delivery_latitude' => 10.3157, 'delivery_longitude' => 123.8854];
    }
}
