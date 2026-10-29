<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class WalletPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_with_gcash_records_the_method_only_when_the_store_accepts_it(): void
    {
        [, $product] = $this->sellerProduct(walletReady: false);
        $buyer = $this->buyerWithCart($product, 2);

        $this->actingAs($buyer)->post(route('checkout.store'), [...$this->delivery(), 'payment_method' => 'gcash', 'wallet_notice' => '1'])
            ->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->refresh()->stock_quantity);

        $product->store->update(['gcash_number' => '09171234567', 'gcash_qr_path' => 'store-wallet-qr/gcash.png']);
        $this->actingAs($buyer)->post(route('checkout.store'), [...$this->delivery(), 'payment_method' => 'gcash'])
            ->assertSessionHasErrors('wallet_notice');
        $this->assertDatabaseCount('orders', 0);

        $this->actingAs($buyer)->post(route('checkout.store'), [...$this->delivery(), 'payment_method' => 'gcash', 'wallet_notice' => '1'])->assertRedirect();

        $this->assertDatabaseHas('payments', ['method' => 'gcash', 'status' => 'pending', 'amount' => '40.00']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.wallet_notice_accepted', 'actor_user_id' => $buyer->id]);
        $this->assertSame(3, $product->refresh()->stock_quantity);
    }

    public function test_store_confirms_a_gcash_payment_before_it_can_confirm_and_refunds_to_cancel(): void
    {
        [$seller, $product] = $this->sellerProduct();
        $buyer = $this->buyerWithCart($product, 2);
        $this->actingAs($buyer)->post(route('checkout.store'), [...$this->delivery(), 'payment_method' => 'gcash', 'wallet_notice' => '1']);
        $sellerOrder = SellerOrder::query()->sole();

        $this->actingAs($seller)->patch(route('seller.orders.update', $sellerOrder), ['status' => 'confirmed'])->assertSessionHasErrors('status');
        $this->actingAs($seller)->patch(route('seller.orders.cod-collection', $sellerOrder))->assertSessionHasErrors('payment');

        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
            ->patch(route('orders.payment-reference', $sellerOrder), ['payment_reference' => '1234567890123'])
            ->assertNotFound();
        $this->actingAs($buyer)->patch(route('orders.payment-reference', $sellerOrder), ['payment_reference' => '1234567890123'])->assertSessionHas('status');

        $this->actingAs($seller)->patch(route('seller.orders.cod-collection', $sellerOrder))->assertSessionHas('status');
        $this->assertNotNull($sellerOrder->refresh()->cod_collected_at);
        $this->assertDatabaseHas('payments', ['order_id' => $sellerOrder->order_id, 'status' => 'paid']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'seller_order.cod_collected', 'entity_id' => $sellerOrder->id]);

        $this->actingAs($buyer)->patch(route('orders.payment-reference', $sellerOrder), ['payment_reference' => '999'])->assertSessionHasErrors('payment_reference');
        $this->actingAs($seller)->patch(route('seller.orders.update', $sellerOrder), ['status' => 'confirmed'])->assertSessionHasNoErrors();

        $this->actingAs($seller)->patch(route('seller.orders.update', $sellerOrder), ['status' => 'cancelled'])->assertSessionHasErrors('refund_reference');
        $this->actingAs($seller)->patch(route('seller.orders.update', $sellerOrder), ['status' => 'cancelled', 'refund_reference' => 'RF 5550001'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('seller_orders', ['id' => $sellerOrder->id, 'status' => 'cancelled', 'refund_reference' => 'RF 5550001']);
        $this->assertSame(5, $product->refresh()->stock_quantity);
    }

    public function test_delivered_wallet_order_earning_is_kept_by_the_store_and_billed_monthly(): void
    {
        [$seller, $product] = $this->sellerProduct();
        $buyer = $this->buyerWithCart($product, 1);
        $this->actingAs($buyer)->post(route('checkout.store'), [...$this->delivery(), 'payment_method' => 'maya', 'wallet_notice' => '1']);
        $sellerOrder = SellerOrder::query()->sole();
        $this->actingAs($buyer)->patch(route('orders.payment-reference', $sellerOrder), ['payment_reference' => 'MAYA123']);
        $this->actingAs($seller)->patch(route('seller.orders.cod-collection', $sellerOrder));

        foreach (['confirmed', 'processing', 'shipped', 'delivered'] as $status) {
            $this->actingAs($seller)->patch(route('seller.orders.update', $sellerOrder), ['status' => $status])->assertSessionHasNoErrors();
        }

        $this->assertNotNull($sellerOrder->earning()->sole()->voided_at);
    }

    public function test_unpaid_wallet_orders_without_a_reference_are_cancelled_and_restock(): void
    {
        [, $product] = $this->sellerProduct();
        $buyer = $this->buyerWithCart($product, 1);
        $this->actingAs($buyer)->post(route('checkout.store'), [...$this->delivery(), 'payment_method' => 'gcash', 'wallet_notice' => '1']);
        $unpaid = SellerOrder::query()->sole();
        $this->buyerWithCart($product, 1, $buyer);
        $this->actingAs($buyer)->post(route('checkout.store'), [...$this->delivery(), 'payment_method' => 'gcash', 'wallet_notice' => '1']);
        $referenced = SellerOrder::query()->whereKeyNot($unpaid->id)->sole();
        $this->actingAs($buyer)->patch(route('orders.payment-reference', $referenced), ['payment_reference' => 'GC777']);

        $this->travel(25)->hours();
        $this->artisan('orders:cancel-unpaid')->assertSuccessful();

        $this->assertSame('cancelled', $unpaid->refresh()->status);
        $this->assertSame('pending', $referenced->refresh()->status);
        $this->assertSame(4, $product->refresh()->stock_quantity);
    }

    public function test_unpaid_bank_transfer_waits_longer_before_it_is_cancelled(): void
    {
        [, $product] = $this->sellerProduct();
        $this->actingAs($this->buyerWithCart($product, 1))->post(route('checkout.store'), [...$this->delivery(), 'payment_method' => 'bank', 'wallet_notice' => '1'])->assertRedirect();
        $sellerOrder = SellerOrder::query()->sole();

        $this->travel(25)->hours();
        $this->artisan('orders:cancel-unpaid')->assertSuccessful();
        $this->assertSame('pending', $sellerOrder->refresh()->status);

        $this->travel(24)->hours();
        $this->artisan('orders:cancel-unpaid')->assertSuccessful();
        $this->assertSame('cancelled', $sellerOrder->refresh()->status);
    }

    public function test_seller_saves_bank_details_and_a_bank_needs_its_name(): void
    {
        [$seller, $product] = $this->sellerProduct(walletReady: false);

        $this->actingAs($seller)->patch(route('seller.store-settings.wallets'), ['method' => 'bank', 'bank_number' => '001234567890'])
            ->assertSessionHasErrors(['bank_name', 'bank_account_name']);
        $this->assertFalse($product->store->refresh()->acceptsWallet('bank'));

        $this->actingAs($seller)->patch(route('seller.store-settings.wallets'), ['method' => 'bank', 'bank_name' => 'BDO', 'bank_account_name' => 'Store Owner', 'bank_number' => '0012-3456 7890'])
            ->assertRedirect(route('seller.store-settings.edit'));

        $this->assertTrue($product->store->refresh()->acceptsWallet('bank'));
        $this->assertSame('001234567890', $product->store->bank_number);
        $this->assertFalse($product->store->acceptsWallet('gcash'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.wallets_updated', 'entity_id' => $product->store_id]);
    }

    public function test_gcash_qr_is_kept_with_its_number_and_the_store_is_offered_at_checkout(): void
    {
        Storage::fake('public');
        [$seller, $product] = $this->sellerProduct(walletReady: false);

        $this->actingAs($seller)->patch(route('seller.store-settings.wallets'), ['method' => 'gcash', 'gcash_qr' => UploadedFile::fake()->image('gcash.png')])
            ->assertSessionHasErrors(['gcash_number' => 'Enter your GCash mobile number.']);
        $this->assertNull($product->store->refresh()->gcash_qr_path);

        $this->actingAs($seller)->patch(route('seller.store-settings.wallets'), ['method' => 'gcash', 'gcash_number' => '+63 917-123 4567', 'gcash_qr' => UploadedFile::fake()->image('gcash.png')])
            ->assertSessionHas('status', 'GCash saved. Buyers can now choose it at checkout.');

        $store = $product->store->refresh();
        $this->assertSame('+639171234567', $store->gcash_number);
        Storage::disk('public')->assertExists($store->gcash_qr_path);
        $this->actingAs($this->buyerWithCart($product, 1))->get(route('checkout.create'))->assertOk()->assertSee('value="gcash"', false)->assertDontSee('value="maya"', false);
        $this->actingAs($seller)->get(route('seller.store-settings.edit'))->assertOk()->assertSee('Live at checkout')->assertSee('Save GCash');
    }

    public function test_each_method_saves_on_its_own_and_can_be_turned_off(): void
    {
        Storage::fake('public');
        [$seller, $product] = $this->sellerProduct();
        $oldMayaQr = UploadedFile::fake()->image('maya.png')->store('store-wallet-qr', 'public');
        $product->store->update(['maya_qr_path' => $oldMayaQr]);

        $this->actingAs($seller)->patch(route('seller.store-settings.wallets'), ['method' => 'maya', 'maya_number' => '09189998888', 'maya_qr' => UploadedFile::fake()->image('new-maya.png')])
            ->assertSessionHasNoErrors();

        $store = $product->store->refresh();
        $this->assertSame('09189998888', $store->maya_number);
        $this->assertSame('09171234567', $store->gcash_number);
        $this->assertTrue($store->acceptsWallet('bank'));
        Storage::disk('public')->assertMissing($oldMayaQr);

        $this->actingAs($seller)->patch(route('seller.store-settings.wallets'), ['method' => 'gcash', 'disable' => '1'])
            ->assertSessionHas('status', 'GCash is turned off. Buyers can no longer choose it at checkout.');

        $store->refresh();
        $this->assertNull($store->gcash_number);
        $this->assertNull($store->gcash_qr_path);
        $this->assertTrue($store->acceptsWallet('maya'));
    }

    public function test_buyer_is_guided_to_pay_each_store_after_placing_a_gcash_order(): void
    {
        [, $firstProduct] = $this->sellerProduct();
        [, $secondProduct] = $this->sellerProduct();
        $buyer = $this->buyerWithCart($firstProduct, 1);
        $this->buyerWithCart($secondProduct, 2, $buyer);
        $firstStore = $firstProduct->store->name;
        $secondStore = $secondProduct->store->name;

        $this->actingAs($buyer)->get(route('checkout.create'))->assertOk()
            ->assertSee("Scan each store's QR after ordering.")->assertSee('2 stores · paid separately');

        $response = $this->actingAs($buyer)->post(route('checkout.store'), [...$this->delivery(), 'payment_method' => 'gcash', 'wallet_notice' => '1']);
        $order = Order::query()->sole();
        $response->assertRedirect(route('orders.placed', $order));

        // Nothing is paid at checkout, so the landing page asks for payment instead of claiming it.
        $this->actingAs($buyer)->get(route('orders.placed', $order))->assertOk()
            ->assertSee('To pay stores directly')->assertSee('Pay stores now')->assertSee(route('orders.show', [$order, 'pay' => 1]), false)
            ->assertDontSee('Payment successful');

        $this->actingAs($buyer)->get(route('orders.show', [$order, 'pay' => 1]))->assertOk()
            ->assertSee('0/2')->assertSee("Pay {$firstStore} with GCash")->assertSee('Send reference · next store');

        $first = $order->sellerOrders()->whereHas('store', fn ($query) => $query->where('name', $firstStore))->sole();
        $this->actingAs($buyer)->patch(route('orders.payment-reference', $first), ['payment_reference' => 'GC123', 'guided' => '1'])
            ->assertRedirect(route('orders.show', $order))->assertSessionHas('guided_pay', true);

        $this->actingAs($buyer)->withSession(['guided_pay' => true])->get(route('orders.show', $order))->assertOk()
            ->assertSee('1/2')->assertSee("Pay {$secondStore} with GCash")->assertSee('Send reference · done')->assertSee('Ref GC123');
    }

    public function test_guide_counts_the_right_store_and_keeps_a_rejected_reference_on_its_own_store(): void
    {
        [, $firstProduct] = $this->sellerProduct();
        [, $secondProduct] = $this->sellerProduct();
        $buyer = $this->buyerWithCart($firstProduct, 1);
        $this->buyerWithCart($secondProduct, 1, $buyer);
        $this->actingAs($buyer)->post(route('checkout.store'), [...$this->delivery(), 'payment_method' => 'gcash', 'wallet_notice' => '1']);
        $order = Order::query()->sole();
        [$first, $second] = [$order->sellerOrders()->where('store_id', $firstProduct->store_id)->sole(), $order->sellerOrders()->where('store_id', $secondProduct->store_id)->sole()];
        $second->update(['payment_reference' => 'PAIDFIRST']);

        $this->actingAs($buyer)->from(route('orders.show', $order))
            ->patch(route('orders.payment-reference', $first), ['payment_reference' => 'BAD#REF', 'seller_order_id' => $first->id, 'guided' => '1'])
            ->assertSessionHasErrors('payment_reference');

        $this->actingAs($buyer)->withSession(['_old_input' => ['payment_reference' => 'BAD#REF', 'seller_order_id' => $first->id, 'guided' => '1'], 'errors' => (new ViewErrorBag)->put('default', new MessageBag(['payment_reference' => ['Invalid.']]))])
            ->get(route('orders.show', $order))->assertOk()
            ->assertSee('Store 1 of 2')
            ->assertSee("Pay {$firstProduct->store->name} with GCash")
            ->assertSee('value="BAD#REF"', false)
            ->assertSee('value="PAIDFIRST"', false)
            ->assertDontSee('Store 2 of 2');
    }

    private function buyerWithCart(Product $product, int $quantity, ?User $buyer = null): User
    {
        $buyer ??= User::factory()->create(['email_verified_at' => now()]);
        Cart::firstOrCreate(['user_id' => $buyer->id])->items()->create(['product_id' => $product->id, 'quantity' => $quantity]);

        return $buyer;
    }

    /**
     * @return array{0: User, 1: Product}
     */
    private function sellerProduct(bool $walletReady = true): array
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
            ...($walletReady ? ['gcash_number' => '09171234567', 'gcash_qr_path' => 'store-wallet-qr/gcash.png', 'maya_number' => '09181234567', 'maya_qr_path' => 'store-wallet-qr/maya.png', 'bank_name' => 'BDO', 'bank_account_name' => 'Store Owner', 'bank_number' => '001234567890'] : []),
        ]);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $seller->id, 'role' => 'owner']);
        $product = Product::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Canvas Tote',
            'slug' => "canvas-tote-{$suffix}",
            'sku' => "TOTE-{$suffix}",
            'price' => '20.00',
            'stock_quantity' => 5,
            'status' => 'active',
        ]);

        return [$seller, $product];
    }

    /**
     * @return array{recipient_name: string, phone: string, address_line: string, city: string, province: string, postal_code: string}
     */
    private function delivery(): array
    {
        return [
            'recipient_name' => 'Ava Buyer',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'postal_code' => '1000',
        ];
    }
}
