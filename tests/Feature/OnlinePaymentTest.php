<?php

namespace Tests\Feature;

use App\Actions\BranchInventory;
use App\Actions\ReturnCaseWorkflow;
use App\Actions\UpdateSellerOrderStatus;
use App\Models\BranchStock;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnCase;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
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

        config()->set('services.paymongo', ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsk_test', 'base_url' => 'https://api.paymongo.com']);
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions/*/expire' => Http::response(['data' => ['id' => 'cs_old']]),
            'api.paymongo.com/v1/checkout_sessions' => Http::response(['data' => ['id' => 'cs_1', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_1']]]),
            'api.paymongo.com/v1/refunds' => Http::response(['data' => ['id' => 'ref_1']]),
            'api.paymongo.com/v2/accounts' => Http::response(['data' => ['id' => 'org_store']], 201),
            'api.paymongo.com/v2/accounts/org_store/identity_verification' => Http::response(['data' => ['url' => 'https://verify.paymongo.com/v_1']]),
            'api.paymongo.com/v2/accounts/org_store' => Http::response(['data' => ['activation_status' => 'activated']]),
        ]);
    }

    public function test_online_checkout_needs_the_pharmacys_paymongo_account_and_pays_it_in_full(): void
    {
        $product = $this->product();
        $buyer = $this->buyerWithCart($product, 2);

        config()->set('services.paymongo.secret_key', null);
        $this->actingAs($buyer)->post(route('checkout.store'), [...$this->delivery(), 'payment_method' => 'gcash'])->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('orders', 0);

        config()->set('services.paymongo.secret_key', 'sk_test_x');
        $order = $this->checkOut($buyer);

        $this->get(route('orders.pay', $order))->assertRedirect('https://checkout.paymongo.com/cs_1');
        // ₱40.00 order, all to the pharmacy's own account; the buyer covers GCash's 2.5% with a ₱1.03 fee line.
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/checkout_sessions')
            && $request['data']['attributes']['payment_method_types'] === ['gcash']
            && ! isset($request['data']['attributes']['split_payment'])
            && collect($request['data']['attributes']['line_items'])->sum(fn (array $line): int => $line['amount'] * $line['quantity']) === 4103);
        $this->assertSame('1.03', (string) $order->payment->refresh()->fee_amount);
        $this->assertSame('cs_1', $order->payment->checkout_session_id);
    }

    public function test_an_overlapping_payment_request_is_refused_without_calling_the_gateway(): void
    {
        $order = $this->checkOut($this->buyerWithCart($this->product(), 2));
        $lock = Cache::lock('online-payment:'.$order->id, 60);
        $this->assertTrue($lock->get());
        try {
            $this->get(route('orders.pay', $order))->assertRedirect(route('orders.show', $order))->assertSessionHasErrors('payment');
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public function test_an_already_expired_session_is_verified_before_creating_its_replacement(): void
    {
        $order = $this->checkOut($this->buyerWithCart($this->product(), 2));
        $order->payment()->update(['checkout_session_id' => 'cs_expired']);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions/cs_expired/expire' => Http::response([], 400),
            'api.paymongo.com/v1/checkout_sessions/cs_expired' => Http::response(['data' => ['attributes' => ['status' => 'expired']]]),
            'api.paymongo.com/v1/checkout_sessions' => Http::response(['data' => ['id' => 'cs_new', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_new']]]),
        ]);

        $this->get(route('orders.pay', $order))->assertRedirect('https://checkout.paymongo.com/cs_new');
        $this->assertSame('cs_new', $order->payment->refresh()->checkout_session_id);
        Http::assertSentCount(3);
    }

    public function test_failed_expiry_does_not_open_a_second_payable_checkout(): void
    {
        $order = $this->checkOut($this->buyerWithCart($this->product(), 2));
        $order->payment()->update(['checkout_session_id' => 'cs_processing']);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions/cs_processing/expire' => Http::response(['errors' => [['detail' => 'private provider detail']]], 400),
            'api.paymongo.com/v1/checkout_sessions/cs_processing' => Http::response(['data' => ['attributes' => ['status' => 'active']]]),
            'api.paymongo.com/v1/checkout_sessions' => Http::response(['data' => ['id' => 'cs_new', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/cs_new']]]),
        ]);

        $this->get(route('orders.pay', $order))->assertRedirect(route('orders.show', $order))->assertSessionHasErrors('payment');

        $this->assertSame('cs_processing', $order->payment->refresh()->checkout_session_id);
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/checkout_sessions'));
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

    public function test_cancelling_a_paid_store_order_refunds_it_in_full(): void
    {
        $order = $this->paidOrder();
        $sellerOrder = $order->sellerOrders()->sole();

        app(UpdateSellerOrderStatus::class)->handle($sellerOrder, 'cancelled', null);

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/refunds')
            && $request['data']['attributes']['amount'] === 4000
            && $request['data']['attributes']['payment_id'] === 'pay_1'
            && $request->hasHeader('Idempotency-Key', 'cancelled-order:'.$sellerOrder->id)
            && ! isset($request['data']['attributes']['split_refund']));
        $this->assertSame('ref_1', $sellerOrder->refresh()->gateway_refund_id);
        $this->assertSame(5, $sellerOrder->items()->first()->product->stock_quantity);
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

    public function test_orders_paid_directly_before_paymongo_are_left_to_support(): void
    {
        $order = $this->checkOut($this->buyerWithCart($this->product(), 2));
        // How an order from before the switch looks: no PayMongo fee and no gateway payment.
        $order->payment->update(['fee_amount' => '0.00']);
        $sellerOrder = $order->sellerOrders()->sole();

        $this->travel(3)->hours();
        $this->artisan('orders:cancel-unpaid')->assertSuccessful();
        $this->assertSame('pending', $sellerOrder->refresh()->status);

        $sellerOrder->update(['cod_collected_at' => now()]);
        try {
            app(UpdateSellerOrderStatus::class)->handle($sellerOrder, 'cancelled', $sellerOrder->store->tenant->members()->first()->user);
            $this->fail('A store cancelled an order paid directly to it.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Ask JMMart support', $exception->errors()['status'][0]);
        }

        app(UpdateSellerOrderStatus::class)->handle($sellerOrder, 'cancelled', User::factory()->administrator()->create());
        $this->assertSame('cancelled', $sellerOrder->refresh()->status);
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/refunds'));
    }

    public function test_an_online_return_refund_is_paid_once_and_cannot_be_set_aside(): void
    {
        $order = $this->paidOrder();
        $sellerOrder = $order->sellerOrders()->sole();
        foreach (['confirmed', 'processing', 'shipped', 'delivered'] as $status) {
            app(UpdateSellerOrderStatus::class)->handle($sellerOrder->refresh(), $status, null);
        }
        $case = ReturnCase::create(['seller_order_id' => $sellerOrder->id, 'buyer_id' => $order->user_id, 'opened_by' => $order->user_id, 'status' => 'awaiting_fulfillment', 'reason' => 'damaged', 'requested_remedy' => 'refund', 'remedy' => 'refund', 'details' => 'The tote arrived torn.']);
        $item = $sellerOrder->items()->first();
        $case->items()->create(['order_item_id' => $item->id, 'quantity' => 1, 'unit_price' => $item->unit_price]);
        $seller = $sellerOrder->store->tenant->members()->first()->user;

        app(ReturnCaseWorkflow::class)->act($case, $seller, 'refund');
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/refunds') && $request['data']['attributes']['amount'] === 2000);

        $case->update(['status' => 'appealed']);
        $this->expectException(ValidationException::class);
        app(ReturnCaseWorkflow::class)->act($case, User::factory()->administrator()->create(), 'reopen');
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

    private function product(): Product
    {
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        $suffix = str()->lower(str()->random(6));
        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => 'active']);
        $store = Store::create([
            'tenant_id' => $tenant->id, 'name' => "Store {$suffix}", 'slug' => "store-{$suffix}", 'phone' => '+639171234567',
            'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active',
        ]);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $seller->id, 'role' => 'owner']);

        return tap(Product::create([
            'tenant_id' => $tenant->id, 'store_id' => $store->id, 'name' => 'Canvas Tote', 'slug' => "canvas-tote-{$suffix}",
            'sku' => "TOTE-{$suffix}", 'price' => '20.00', 'stock_quantity' => 5, 'status' => 'active',
        ]), fn (Product $product) => $this->stock($product, (int) (5)));
    }

    /**
     * @return array{recipient_name: string, phone: string, address_line: string, city: string, province: string, postal_code: string}
     */
    private function delivery(): array
    {
        return ['recipient_name' => 'Ava Buyer', 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'province' => 'Metro Manila', 'postal_code' => '1000'];
    }

    /**
     * Sets a product's (or variant's) units on hand at its first branch, keeping the company total in step.
     */
    private function stock(Product $product, int $quantity, ?ProductVariant $variant = null): void
    {
        BranchStock::query()->updateOrCreate(
            ['store_id' => $product->store_id, 'product_id' => $product->id, 'variant_key' => $variant?->id ?? 'default'],
            ['product_variant_id' => $variant?->id, 'quantity' => $quantity],
        );
        app(BranchInventory::class)->syncTotals($product);
    }
}
