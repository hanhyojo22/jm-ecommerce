<?php

namespace Tests\Feature;

use App\Actions\IssuePlatformFeeStatements;
use App\Actions\UpdateSellerOrderStatus;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerEarning;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OnlinePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.paymongo', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsk_test', 'merchant_id' => 'org_jmmart', 'base_url' => 'https://api.paymongo.com']);
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions/*/expire' => Http::response(['data' => ['id' => 'cs_old']]),
            'api.paymongo.com/v1/checkout_sessions' => Http::response(['data' => ['id' => 'cs_1', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_1']]]),
            'api.paymongo.com/v1/refunds' => Http::response(['data' => ['id' => 'ref_1']]),
            'api.paymongo.com/v2/accounts' => Http::response(['data' => ['id' => 'org_store']], 201),
            'api.paymongo.com/v2/accounts/org_store/identity_verification' => Http::response(['data' => ['url' => 'https://verify.paymongo.com/v_1']]),
            'api.paymongo.com/v2/accounts/org_store' => Http::response(['data' => ['activation_status' => 'activated']]),
        ]);
    }

    public function test_online_checkout_needs_a_connected_store_and_splits_its_share(): void
    {
        $product = $this->product(connected: false);
        $buyer = $this->buyerWithCart($product, 2);

        $this->actingAs($buyer)->post(route('checkout.store'), [...$this->delivery(), 'payment_method' => 'gcash'])->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);

        $product->store->update(['paymongo_account_id' => 'org_a', 'paymongo_account_status' => 'activated']);
        $order = $this->checkOut($buyer);

        $this->get(route('orders.pay', $order))->assertRedirect('https://checkout.paymongo.com/cs_1');
        // ₱40.00 order: the store gets ₱39.60 after JMMart's 1%; the buyer covers GCash's 2.5% with a ₱1.03 fee line.
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/checkout_sessions')
            && $request['data']['attributes']['payment_method_types'] === ['gcash']
            && $request['data']['attributes']['split_payment']['recipients'] === [['merchant_id' => 'org_a', 'split_type' => 'fixed', 'value' => 3960]]
            && collect($request['data']['attributes']['line_items'])->sum(fn (array $line): int => $line['amount'] * $line['quantity']) === 4103);
        $this->assertSame('1.03', (string) $order->payment->refresh()->fee_amount);
        $this->assertSame('cs_1', $order->payment->checkout_session_id);
    }

    public function test_signed_webhook_marks_the_order_paid_once_and_lets_the_store_confirm(): void
    {
        $order = $this->checkOut($this->buyerWithCart($this->product(), 2));
        $this->get(route('orders.pay', $order));
        $sellerOrder = $order->sellerOrders()->sole();

        $this->expectConfirmationRefused($sellerOrder);
        $this->webhook('evt_1', 'cs_1', signature: 'forged')->assertUnauthorized();
        $this->assertSame('pending', $order->payment->refresh()->status);

        $this->webhook('evt_1', 'cs_1')->assertOk()->assertJson(['status' => 'ok']);
        $this->webhook('evt_1', 'cs_1')->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertSame('paid', $order->payment->refresh()->status);
        $this->assertSame('pay_1', $order->payment->gateway_payment_id);
        $this->assertNotNull($sellerOrder->refresh()->cod_collected_at);
        $this->actingAs($order->user)->get(route('orders.placed', $order))->assertSee('Payment successful');
        app(UpdateSellerOrderStatus::class)->handle($sellerOrder, 'confirmed', $sellerOrder->store->tenant->members()->first()->user);
        $this->assertSame('confirmed', $sellerOrder->refresh()->status);
    }

    public function test_cancelling_a_paid_store_order_refunds_it_from_the_store_and_the_fee(): void
    {
        $order = $this->paidOrder();
        $sellerOrder = $order->sellerOrders()->sole();

        app(UpdateSellerOrderStatus::class)->handle($sellerOrder, 'cancelled', null);

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/refunds')
            && $request['data']['attributes']['amount'] === 4000
            && $request['data']['attributes']['payment_id'] === 'pay_1'
            && $request['data']['attributes']['split_refund']['refund_sources'] === [
                ['merchant_id' => 'org_a', 'split_type' => 'fixed', 'value' => 3960],
                ['merchant_id' => 'org_jmmart', 'split_type' => 'fixed', 'value' => 40],
            ]);
        $this->assertSame('ref_1', $sellerOrder->refresh()->gateway_refund_id);
        $this->assertSame(5, $sellerOrder->items()->first()->product->stock_quantity);
    }

    public function test_a_delivered_online_order_is_neither_billed_nor_paid_out(): void
    {
        $order = $this->paidOrder();
        $sellerOrder = $order->sellerOrders()->sole();
        foreach (['confirmed', 'processing', 'shipped', 'delivered'] as $status) {
            app(UpdateSellerOrderStatus::class)->handle($sellerOrder->refresh(), $status, null);
        }

        $earning = SellerEarning::query()->where('seller_order_id', $sellerOrder->id)->sole();
        $this->assertTrue($earning->fee_collected_at_payment);
        $this->assertNotNull($earning->voided_at);
        $this->assertSame('0.40', (string) $earning->commission_amount);
        $this->assertSame('0.00', app(IssuePlatformFeeStatements::class)->preview($sellerOrder->tenant_id)['commission']);
    }

    public function test_an_unpaid_online_order_is_released_after_the_payment_window(): void
    {
        $order = $this->checkOut($this->buyerWithCart($this->product(), 2));
        $this->get(route('orders.pay', $order));

        $this->travel(3)->hours();
        $this->artisan('orders:cancel-unpaid')->assertSuccessful();

        $sellerOrder = $order->sellerOrders()->sole();
        $this->assertSame('cancelled', $sellerOrder->status);
        $this->assertSame(5, $sellerOrder->items()->first()->product->stock_quantity);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/checkout_sessions/cs_1/expire'));
    }

    public function test_only_the_store_owner_connects_paymongo(): void
    {
        $product = $this->product(connected: false);
        $owner = $product->store->tenant->members()->first()->user;
        $manager = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $product->tenant_id, 'user_id' => $manager->id, 'role' => 'manager']);

        $this->actingAs($manager)->post(route('seller.online-payments.store'))->assertForbidden();
        $this->actingAs($owner)->post(route('seller.online-payments.store'))->assertRedirect('https://verify.paymongo.com/v_1');
        $this->assertSame('org_store', $product->store->refresh()->paymongo_account_id);
        $this->assertFalse($product->store->acceptsOnlinePayments());

        $this->post(route('seller.online-payments.refresh'))->assertRedirect(route('seller.store-settings.edit'));
        $this->assertTrue($product->store->refresh()->acceptsOnlinePayments());
    }

    private function paidOrder(): Order
    {
        $order = $this->checkOut($this->buyerWithCart($this->product(), 2));
        $this->get(route('orders.pay', $order));
        $this->webhook('evt_paid', 'cs_1')->assertOk();

        return $order->refresh();
    }

    private function checkOut(User $buyer): Order
    {
        $this->actingAs($buyer)->post(route('checkout.store'), [...$this->delivery(), 'payment_method' => 'gcash'])->assertRedirectContains('/pay');

        return Order::query()->latest()->firstOrFail();
    }

    private function webhook(string $eventId, string $sessionId, ?string $signature = null): TestResponse
    {
        $body = json_encode(['data' => ['id' => $eventId, 'attributes' => ['type' => 'checkout_session.payment.paid', 'livemode' => false, 'data' => [
            'id' => $sessionId,
            'attributes' => ['payments' => [['id' => 'pay_1', 'attributes' => ['status' => 'paid']]]],
        ]]]]);
        $signature ??= hash_hmac('sha256', '1700000000.'.$body, 'whsk_test');

        return $this->call('POST', route('paymongo.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => "t=1700000000,te={$signature},li="], $body);
    }

    private function expectConfirmationRefused(SellerOrder $sellerOrder): void
    {
        try {
            app(UpdateSellerOrderStatus::class)->handle($sellerOrder, 'confirmed', null);
            $this->fail('An unpaid online order was confirmed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
    }

    private function buyerWithCart(Product $product, int $quantity): User
    {
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        Cart::create(['user_id' => $buyer->id])->items()->create(['product_id' => $product->id, 'quantity' => $quantity]);

        return $buyer;
    }

    private function product(bool $connected = true): Product
    {
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        $suffix = str()->lower(str()->random(6));
        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => 'active', 'platform_fee_accepted_at' => now()->subMonth(), 'platform_fee_rate' => '0.01']);
        $store = Store::create([
            'tenant_id' => $tenant->id, 'name' => "Store {$suffix}", 'slug' => "store-{$suffix}", 'phone' => '+639171234567',
            'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active',
            ...($connected ? ['paymongo_account_id' => 'org_a', 'paymongo_account_status' => 'activated'] : []),
        ]);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $seller->id, 'role' => 'owner']);

        return Product::create([
            'tenant_id' => $tenant->id, 'store_id' => $store->id, 'name' => 'Canvas Tote', 'slug' => "canvas-tote-{$suffix}",
            'sku' => "TOTE-{$suffix}", 'price' => '20.00', 'stock_quantity' => 5, 'status' => 'active',
        ]);
    }

    /**
     * @return array{recipient_name: string, phone: string, address_line: string, city: string, province: string, postal_code: string}
     */
    private function delivery(): array
    {
        return ['recipient_name' => 'Ava Buyer', 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'province' => 'Metro Manila', 'postal_code' => '1000'];
    }
}
