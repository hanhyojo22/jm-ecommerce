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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use RuntimeException;
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
        Storage::fake('chat_attachments');

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
            'discount_document' => UploadedFile::fake()->image('id.jpg'),
        ])->assertCreated()->assertJsonPath('data.discount_amount', '6.00');

        $card = DiscountCard::query()->sole();
        $this->assertSame(['senior', '0123'], [$card->type, $card->last4]);
        Storage::disk('chat_attachments')->assertExists($card->document_path);
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

        // No new photo: the order gets its own copy of the saved one, which outlives the saved ID.
        $this->assertStringStartsWith('discount-documents/'.$order->id.'/', $order->discount_document_path);
        $this->assertSame('id.jpg', $order->discount_document_name);
        $this->actingAs($buyer)->deleteJson('/api/v1/discount-cards/'.$card->id)->assertNoContent();
        Storage::disk('chat_attachments')->assertMissing($card->document_path);
        Storage::disk('chat_attachments')->assertExists($order->discount_document_path);
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
            'discount_document' => UploadedFile::fake()->image('id.jpg'),
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
            'discount_document' => UploadedFile::fake()->image('id.jpg'),
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
            'discount_document' => UploadedFile::fake()->image('id.jpg'),
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
            ->assertSee('•••• 0123 · Add a photo of this ID below')
            ->assertDontSee('SC-2026-00123');

        $checkout = [
            'recipient_name' => 'Ava Buyer', 'phone' => '+639171234567', 'address_line' => '100 Colon Street',
            'city' => 'Cebu', 'payment_method' => 'cod', 'discount_card_id' => $card->id,
            // Typed fields sent with a saved ID are ignored on the web too.
            'discount_card' => ['type' => 'pwd', 'id_number' => 'WRONG-1', 'name' => 'Wrong Name'],
        ];
        // An ID saved before photos were kept needs one with the order, which is then kept with the ID.
        $this->post(route('checkout.store'), $checkout)->assertSessionHasErrors('discount_document');
        $this->post(route('checkout.store'), [...$checkout, 'discount_document' => UploadedFile::fake()->image('senior.jpg')])->assertRedirectContains('/placed');
        Storage::disk('chat_attachments')->assertExists($card->refresh()->document_path);

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
        Storage::disk('chat_attachments')->assertMissing($card->document_path);
    }

    public function test_a_new_photo_with_a_saved_id_replaces_its_old_photo(): void
    {
        $buyer = $this->buyer();
        $this->checkoutWithCard($buyer, 'SC-0001')->assertCreated();
        $card = DiscountCard::query()->sole();
        $oldPath = $card->document_path;

        $this->fillCart($buyer);
        $this->actingAs($buyer)->postJson('/api/v1/checkout', [
            ...$this->delivery(),
            'discount_card_id' => $card->id,
            'discount_document' => UploadedFile::fake()->image('renewed.jpg'),
        ])->assertCreated();

        $card->refresh();
        $this->assertNotSame($oldPath, $card->document_path);
        $this->assertSame('renewed.jpg', $card->document_name);
        Storage::disk('chat_attachments')->assertMissing($oldPath);
        Storage::disk('chat_attachments')->assertExists($card->document_path);
        $this->assertEqualsCanonicalizing(['id.jpg', 'renewed.jpg'], Order::query()->pluck('discount_document_name')->all());
    }

    public function test_removing_the_discount_at_handover_also_drops_the_saved_id(): void
    {
        $buyer = $this->buyerWithCart();
        $this->actingAs($buyer)->postJson('/api/v1/checkout', [
            ...$this->delivery(),
            'payment_method' => 'cod',
            'discount_card' => ['type' => 'senior', 'id_number' => 'SC-1', 'name' => 'Lola Ava'],
            'save_discount_card' => true,
            'discount_document' => UploadedFile::fake()->image('id.jpg'),
        ])->assertCreated();

        $order = Order::with('sellerOrders')->sole();
        $this->assertDatabaseCount('discount_cards', 1);

        $this->actingAs($this->staff('manager'))->delete(route('seller.orders.senior-pwd-discount.destroy', $order->sellerOrders->sole()))
            ->assertSessionHas('status', 'Discount removed. Collect ₱220.00.');

        $this->assertDatabaseCount('discount_cards', 0);
        $this->assertSame('SC-1', $order->refresh()->discount_card_number);
    }

    public function test_checkout_stores_a_discount_document_privately_and_staff_can_review_it_without_changing_totals(): void
    {
        Storage::fake('chat_attachments');
        $buyer = $this->buyerWithCart();
        $this->actingAs($buyer)->post(route('checkout.store'), [
            ...$this->delivery(),
            'discount_card' => ['type' => 'senior', 'id_number' => 'SC-4821', 'name' => 'Lola Ava'],
            'discount_document' => UploadedFile::fake()->image('senior-id.jpg'),
        ])->assertSessionHasNoErrors();
        $order = Order::sole();
        $sellerOrder = $order->sellerOrders()->sole();
        $staff = $this->staff();
        Storage::disk('chat_attachments')->assertExists($order->discount_document_path);
        $this->assertArrayNotHasKey('discount_document_path', $order->toArray());
        $before = [(string) $order->total, (string) $order->discount_amount, (string) $order->payment->amount];

        $this->actingAs($staff)->get(route('seller.orders.discount-document.show', $sellerOrder))
            ->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('seller.orders.show', $sellerOrder), ['X-Order-Modal' => '1'])
            ->assertSee('Senior citizen discount ID')->assertSee('Mark ID reviewed');
        $this->post(route('seller.orders.discount-document.review', $sellerOrder))->assertSessionHasNoErrors();
        $this->post(route('seller.orders.discount-document.review', $sellerOrder))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('seller_orders', ['id' => $sellerOrder->id, 'discount_reviewed_by' => $staff->id, 'status' => 'pending']);
        $this->assertNotNull($sellerOrder->refresh()->discount_reviewed_at);
        $this->assertSame($before, [(string) $order->refresh()->total, (string) $order->discount_amount, (string) $order->payment->amount]);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.discount_id_reviewed', 'entity_id' => $sellerOrder->id]);
    }

    public function test_discount_documents_and_reviews_are_scoped_to_the_fulfilling_branch(): void
    {
        Storage::fake('chat_attachments');
        $buyer = $this->buyerWithCart();
        $this->actingAs($buyer)->post(route('checkout.store'), [
            ...$this->delivery(),
            'discount_card' => ['type' => 'pwd', 'id_number' => 'PWD-5501', 'name' => 'Ava Buyer'],
            'discount_document' => UploadedFile::fake()->image('pwd.jpg'),
        ])->assertSessionHasNoErrors();
        $sellerOrder = Order::sole()->sellerOrders()->sole();
        $otherBranch = Store::create(['tenant_id' => $this->branch->tenant_id, 'name' => 'Other branch', 'slug' => 'other-branch', 'status' => 'active', 'phone' => '+639171234567', 'address_line' => 'Other Street', 'city' => 'Cebu']);
        $outsider = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $this->branch->tenant_id, 'store_id' => $otherBranch->id, 'user_id' => $outsider->id, 'role' => 'staff']);

        $this->actingAs($outsider)->get(route('seller.orders.discount-document.show', $sellerOrder))->assertNotFound();
        $this->post(route('seller.orders.discount-document.review', $sellerOrder))->assertNotFound();
        $this->actingAs($buyer)->get(route('seller.orders.discount-document.show', $sellerOrder))->assertForbidden();
        $this->post(route('seller.orders.discount-document.review', $sellerOrder))->assertForbidden();
        $this->assertNull($sellerOrder->refresh()->discount_reviewed_at);
    }

    public function test_discount_document_upload_rejects_invalid_files_and_a_missing_discount_card(): void
    {
        Storage::fake('chat_attachments');
        $buyer = $this->buyerWithCart();
        $this->actingAs($buyer)->post(route('checkout.store'), [
            ...$this->delivery(), 'discount_document' => UploadedFile::fake()->image('id.jpg'),
        ])->assertSessionHasErrors('discount_document');
        $this->post(route('checkout.store'), [
            ...$this->delivery(),
            'discount_card' => ['type' => 'pwd', 'id_number' => 'PWD-1', 'name' => 'Ava Buyer'],
            'discount_document' => UploadedFile::fake()->create('id.html', 10, 'text/html'),
        ])->assertSessionHasErrors('discount_document');
        $this->post(route('checkout.store'), [
            ...$this->delivery(),
            'discount_card' => ['type' => 'pwd', 'id_number' => 'PWD-1', 'name' => 'Ava Buyer'],
            'discount_document' => UploadedFile::fake()->image('id.jpg')->size(8193),
        ])->assertSessionHasErrors('discount_document');

        $this->assertDatabaseCount('orders', 0);
        Storage::disk('chat_attachments')->assertEmpty();
    }

    public function test_api_checkout_requires_a_photo_of_the_discount_id(): void
    {
        $buyer = $this->buyerWithCart();
        $card = ['type' => 'pwd', 'id_number' => 'PWD-5501', 'name' => 'Ava Buyer'];
        $this->actingAs($buyer)->postJson('/api/v1/checkout', [...$this->delivery(), 'discount_card' => $card])
            ->assertUnprocessable()
            ->assertJsonPath('error.fields.discount_document.0', 'Add a photo of your senior citizen or PWD ID to get the discount.');
        $this->assertDatabaseCount('orders', 0);

        $this->actingAs($buyer)->postJson('/api/v1/checkout', [
            ...$this->delivery(),
            'discount_card' => $card,
            'discount_document' => UploadedFile::fake()->image('pwd.jpg'),
        ])->assertCreated();
        $order = Order::sole();
        Storage::disk('chat_attachments')->assertExists($order->discount_document_path);
        $this->assertSame('pwd.jpg', $order->discount_document_name);
    }

    public function test_discount_review_rejects_orders_without_documents_and_completed_orders(): void
    {
        Storage::fake('chat_attachments');
        $buyer = $this->buyerWithCart();
        $this->actingAs($buyer)->post(route('checkout.store'), [
            ...$this->delivery(), 'discount_card' => ['type' => 'pwd', 'id_number' => 'PWD-1', 'name' => 'Ava Buyer'],
            'discount_document' => UploadedFile::fake()->image('id.jpg'),
        ])->assertSessionHasNoErrors();
        $order = Order::sole();
        // Orders placed before the photo was required have none.
        $order->forceFill(['discount_document_path' => null])->save();
        $sellerOrder = $order->sellerOrders()->sole();
        $this->actingAs($this->staff())->get(route('seller.orders.show', $sellerOrder), ['X-Order-Modal' => '1'])
            ->assertSee('No ID photo was submitted.')->assertDontSee('Mark ID reviewed');
        $this->post(route('seller.orders.discount-document.review', $sellerOrder))->assertSessionHasErrors('discount');
        $order->forceFill(['discount_document_path' => 'id.jpg', 'discount_document_mime' => 'image/jpeg'])->save();
        foreach (['delivered', 'cancelled'] as $status) {
            $sellerOrder->update(['status' => $status]);
            $this->post(route('seller.orders.discount-document.review', $sellerOrder))->assertSessionHasErrors('discount');
        }
        $this->assertNull($sellerOrder->refresh()->discount_reviewed_at);
    }

    public function test_a_failed_checkout_does_not_leave_a_private_id_document_in_storage(): void
    {
        Storage::fake('chat_attachments');
        $buyer = $this->buyerWithCart();
        Order::saving(function (Order $order): void {
            if ($order->discount_document_path !== null) {
                throw new RuntimeException('The database is unavailable.');
            }
        });

        $this->actingAs($buyer)->post(route('checkout.store'), [
            ...$this->delivery(),
            'discount_card' => ['type' => 'pwd', 'id_number' => 'PWD-1', 'name' => 'Ava Buyer'],
            'discount_document' => UploadedFile::fake()->image('id.jpg'),
        ])->assertServerError();

        $this->assertDatabaseCount('orders', 0);
        Storage::disk('chat_attachments')->assertEmpty();
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
            'discount_document' => UploadedFile::fake()->image('id.jpg'),
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
