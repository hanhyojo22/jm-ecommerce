<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->actingAs($seller)->patch(route('seller.store-settings.wallets'), ['bank_number' => '001234567890'])
            ->assertSessionHasErrors(['bank_name', 'bank_account_name']);
        $this->assertFalse($product->store->refresh()->acceptsWallet('bank'));

        $this->actingAs($seller)->patch(route('seller.store-settings.wallets'), ['bank_name' => 'BDO', 'bank_account_name' => 'Store Owner', 'bank_number' => '001234567890'])
            ->assertRedirect(route('seller.store-settings.edit'));

        $this->assertTrue($product->store->refresh()->acceptsWallet('bank'));
        $this->assertFalse($product->store->acceptsWallet('gcash'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.wallets_updated', 'entity_id' => $product->store_id]);
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
