<?php

namespace Tests\Feature;

use App\Models\BranchStock;
use App\Models\Cart;
use App\Models\DiscountCard;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DiscountCardTest extends TestCase
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

    public function test_a_saved_id_reused_at_checkout_gives_the_same_discount_and_order_copy(): void
    {
        $buyer = $this->buyerWithCart();

        $this->actingAs($buyer)->postJson('/api/v1/checkout', [
            ...$this->delivery(),
            'payment_method' => 'cod',
            'discount_card' => ['type' => 'senior', 'id_number' => 'SC-2026-00123', 'name' => 'Lola Ava'],
            'save_discount_card' => true,
        ])->assertCreated()->assertJsonPath('data.discount_amount', '6.00');

        $card = DiscountCard::query()->sole();
        $this->assertSame(['senior', '0123'], [$card->type, $card->last4]);
        $this->assertSame('SC-2026-00123', $card->id_number);
        $this->assertSame('Lola Ava', $card->name);

        $this->fillCart($buyer);
        $second = $this->actingAs($buyer)->postJson('/api/v1/checkout', [
            ...$this->delivery(),
            'payment_method' => 'cod',
            'discount_card_id' => $card->id,
            // Typed fields sent with a saved ID are ignored; the saved copy wins.
            'discount_card' => ['type' => 'pwd', 'id_number' => 'WRONG-1', 'name' => 'Wrong Name'],
        ])->assertCreated()
            ->assertJsonPath('data.subtotal', '171.00')
            ->assertJsonPath('data.discount_amount', '6.00')
            ->assertJsonPath('data.total', '214.00')
            ->assertJsonPath('data.discount_card.type', 'senior')
            ->assertJsonPath('data.discount_card.id_number', '•••••••••0123');

        $order = Order::query()->findOrFail($second->json('data.id'));
        $this->assertSame(['senior', 'SC-2026-00123', 'Lola Ava'], [$order->discount_card_type, $order->discount_card_number, $order->discount_card_name]);
        $this->assertSame('6.00', (string) $order->discount_amount);
        $this->assertDatabaseCount('orders', 2);
    }

    public function test_the_app_saves_an_id_sent_as_form_fields_like_a_prescription_upload(): void
    {
        $buyer = $this->buyerWithCart();

        // With prescription pages the app posts multipart, where every value arrives as a string.
        $this->actingAs($buyer)->post('/api/v1/checkout', [
            ...$this->delivery(),
            'payment_method' => 'cod',
            'discount_card' => ['type' => 'pwd', 'id_number' => 'PWD-55501', 'name' => 'Ava Cruz'],
            'save_discount_card' => '1',
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(['pwd', '5501'], [DiscountCard::query()->sole()->type, DiscountCard::query()->sole()->last4]);
    }

    public function test_nothing_is_saved_without_the_checkbox(): void
    {
        $buyer = $this->buyerWithCart();

        $this->actingAs($buyer)->postJson('/api/v1/checkout', [
            ...$this->delivery(),
            'payment_method' => 'cod',
            'discount_card' => ['type' => 'senior', 'id_number' => 'SC-2026-00123', 'name' => 'Lola Ava'],
        ])->assertCreated();

        $this->assertDatabaseCount('discount_cards', 0);
    }

    public function test_the_list_stops_at_three_and_the_same_id_is_only_saved_once(): void
    {
        $buyer = $this->buyer();

        $this->checkoutWithCard($buyer, 'SC-0001')->assertCreated();
        $this->checkoutWithCard($buyer, 'SC-0001')->assertCreated();
        $this->assertDatabaseCount('discount_cards', 1);

        $this->checkoutWithCard($buyer, 'SC-0002')->assertCreated();
        $this->checkoutWithCard($buyer, 'SC-0003')->assertCreated();
        $this->assertDatabaseCount('discount_cards', 3);

        $this->checkoutWithCard($buyer, 'SC-0004')->assertCreated();
        $this->assertDatabaseCount('discount_cards', 3);
        $this->assertDatabaseMissing('discount_cards', ['last4' => '0004']);
        $this->assertDatabaseCount('orders', 5);
    }

    public function test_another_customer_cannot_use_or_delete_a_saved_id(): void
    {
        $owner = $this->buyer();
        $card = DiscountCard::factory()->for($owner, 'user')->create([
            'type' => 'senior', 'id_number' => 'SC-9999', 'name' => 'Lola Ava', 'last4' => '9999',
        ]);
        $other = $this->buyer();
        $this->fillCart($other);

        $this->actingAs($other)->postJson('/api/v1/checkout', [
            ...$this->delivery(),
            'payment_method' => 'cod',
            'discount_card_id' => $card->id,
        ])->assertNotFound();
        $this->assertDatabaseCount('orders', 0);

        $this->actingAs($other)->deleteJson('/api/v1/discount-cards/'.$card->id)->assertNotFound();
        $this->assertDatabaseHas('discount_cards', ['id' => $card->id]);

        $this->actingAs($owner)->deleteJson('/api/v1/discount-cards/'.$card->id)->assertNoContent();
        $this->assertDatabaseCount('discount_cards', 0);
    }

    public function test_deleting_a_saved_id_leaves_an_earlier_order_unchanged(): void
    {
        $buyer = $this->buyerWithCart();
        $this->actingAs($buyer)->postJson('/api/v1/checkout', [
            ...$this->delivery(),
            'payment_method' => 'cod',
            'discount_card' => ['type' => 'pwd', 'id_number' => 'PWD-778', 'name' => 'Ava Buyer'],
            'save_discount_card' => true,
        ])->assertCreated();

        $order = Order::query()->sole();
        $card = DiscountCard::query()->sole();
        $this->actingAs($buyer)->deleteJson('/api/v1/discount-cards/'.$card->id)->assertNoContent();

        $order->refresh();
        $this->assertSame(['pwd', 'PWD-778', 'Ava Buyer', '6.00'], [
            $order->discount_card_type, $order->discount_card_number, $order->discount_card_name, (string) $order->discount_amount,
        ]);
    }

    public function test_the_quote_and_the_saved_list_only_expose_the_masked_number(): void
    {
        $buyer = $this->buyer();
        DiscountCard::factory()->for($buyer, 'user')->create([
            'type' => 'senior', 'id_number' => 'SC-2026-00123', 'name' => 'Lola Ava', 'last4' => '0123',
        ]);
        $this->fillCart($buyer);

        $quote = $this->actingAs($buyer)->getJson('/api/v1/checkout')->assertOk()
            ->assertJsonPath('data.discount_cards.0.type', 'senior')
            ->assertJsonPath('data.discount_cards.0.name', 'Lola Ava')
            ->assertJsonPath('data.discount_cards.0.id_number_masked', '•••••••••0123');
        $this->assertStringNotContainsString('SC-2026-00123', $quote->getContent());

        $list = $this->actingAs($buyer)->getJson('/api/v1/discount-cards')->assertOk()
            ->assertJsonPath('data.0.id_number_masked', '•••••••••0123');
        $this->assertStringNotContainsString('SC-2026-00123', $list->getContent());
    }

    public function test_the_web_checkout_and_profile_use_a_saved_id(): void
    {
        $buyer = $this->buyer();
        $card = DiscountCard::factory()->for($buyer, 'user')->create([
            'type' => 'senior', 'id_number' => 'SC-2026-00123', 'name' => 'Lola Ava', 'last4' => '0123',
        ]);
        $cart = Cart::create(['user_id' => $buyer->id]);
        $cart->items()->create(['product_id' => $this->paracetamol->id, 'quantity' => 3]);

        $this->actingAs($buyer)->get(route('checkout.create'))
            ->assertOk()
            ->assertSee('name="discount_card_id"', false)
            ->assertSee('value="'.$card->id.'"', false)
            ->assertSee("cardId: '{$card->id}'", false)
            ->assertSee('•••• 0123')
            ->assertDontSee('SC-2026-00123');

        $this->post(route('checkout.store'), [
            'recipient_name' => 'Ava Buyer', 'phone' => '+639171234567', 'address_line' => '100 Colon Street',
            'city' => 'Cebu', 'payment_method' => 'cod', 'discount_card_id' => $card->id,
            // Typed fields sent with a saved ID are ignored on the web too.
            'discount_card' => ['type' => 'pwd', 'id_number' => 'WRONG-1', 'name' => 'Wrong Name'],
        ])->assertRedirectContains('/placed');

        $order = Order::query()->sole();
        $this->assertSame(['senior', 'SC-2026-00123', 'Lola Ava'], [
            $order->discount_card_type, $order->discount_card_number, $order->discount_card_name,
        ]);

        $this->actingAs($buyer)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Saved discount IDs')
            ->assertSee('•••• 0123')
            ->assertDontSee('SC-2026-00123');

        $this->actingAs($this->buyer())->delete(route('profile.discount-cards.destroy', $card))->assertNotFound();
        $this->assertDatabaseHas('discount_cards', ['id' => $card->id]);

        $this->actingAs($buyer)->delete(route('profile.discount-cards.destroy', $card))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'Saved discount ID removed.');
        $this->assertDatabaseCount('discount_cards', 0);
        $this->assertSame('SC-2026-00123', $order->refresh()->discount_card_number);
    }

    public function test_removing_the_discount_at_handover_also_drops_the_saved_id(): void
    {
        $buyer = $this->buyerWithCart();
        $this->actingAs($buyer)->postJson('/api/v1/checkout', [
            ...$this->delivery(),
            'payment_method' => 'cod',
            'discount_card' => ['type' => 'senior', 'id_number' => 'SC-1', 'name' => 'Lola Ava'],
            'save_discount_card' => true,
        ])->assertCreated();

        $order = Order::with('sellerOrders')->sole();
        $this->assertDatabaseCount('discount_cards', 1);

        $this->actingAs($this->staff('manager'))->delete(route('seller.orders.senior-pwd-discount.destroy', $order->sellerOrders->sole()))
            ->assertSessionHas('status', 'Discount removed. Collect ₱220.00.');

        $this->assertDatabaseCount('discount_cards', 0);
        $this->assertSame('SC-1', $order->refresh()->discount_card_number);
    }

    private function product(string $name, string $price, ?string $generic): Product
    {
        $product = Product::create([
            'tenant_id' => $this->branch->tenant_id, 'store_id' => $this->branch->id, 'name' => $name, 'slug' => str($name)->slug()->toString(),
            'sku' => str($name)->slug()->upper()->toString(), 'price' => $price, 'stock_quantity' => 100, 'status' => 'active', 'generic_name' => $generic,
        ]);
        BranchStock::create(['store_id' => $this->branch->id, 'product_id' => $product->id, 'variant_key' => 'default', 'quantity' => 100]);

        return $product;
    }

    private function buyer(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function buyerWithCart(): User
    {
        $buyer = $this->buyer();
        $this->fillCart($buyer);

        return $buyer->refresh();
    }

    private function fillCart(User $buyer): void
    {
        $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['branch' => $this->branch->id, 'product_id' => $this->paracetamol->id, 'quantity' => 3])->assertCreated();
        $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['product_id' => $this->shampoo->id, 'quantity' => 1])->assertCreated();
    }

    private function checkoutWithCard(User $buyer, string $number): TestResponse
    {
        $this->fillCart($buyer);

        return $this->actingAs($buyer)->postJson('/api/v1/checkout', [
            ...$this->delivery(),
            'payment_method' => 'cod',
            'discount_card' => ['type' => 'senior', 'id_number' => $number, 'name' => 'Lola Ava'],
            'save_discount_card' => true,
        ]);
    }

    private function staff(string $role = 'staff'): User
    {
        $user = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $this->branch->tenant_id, 'store_id' => $this->branch->id, 'user_id' => $user->id, 'role' => $role]);

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
