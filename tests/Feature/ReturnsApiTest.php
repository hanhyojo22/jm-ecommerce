<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnCase;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_buyer_opens_a_return_case_for_a_delivered_order(): void
    {
        [$buyer, $sellerOrder, $item] = $this->deliveredOrder();

        $response = $this->actingAs($buyer)
            ->postJson("/api/v1/orders/seller-orders/{$sellerOrder->id}/returns", [
                'reason' => 'damaged',
                'requested_remedy' => 'refund',
                'details' => 'The item arrived with a cracked body.',
                'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'awaiting_seller')
            ->assertJsonPath('data.reason', 'damaged')
            ->assertJsonPath('data.items.0.order_item_id', $item->id)
            ->assertJsonPath('data.items.0.quantity', 1)
            ->assertJsonPath('data.seller_order.id', $sellerOrder->id)
            ->assertJsonPath('data.can_respond', true);

        $caseId = $response->json('data.id');

        $this->assertDatabaseHas('return_cases', [
            'id' => $caseId,
            'seller_order_id' => $sellerOrder->id,
            'buyer_id' => $buyer->id,
            'status' => 'awaiting_seller',
        ]);
        $this->assertDatabaseHas('return_case_items', ['order_item_id' => $item->id, 'quantity' => 1]);

        $this->actingAs($buyer)
            ->getJson("/api/v1/returns/{$caseId}")
            ->assertOk()
            ->assertJsonPath('data.id', $caseId);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/returns/{$caseId}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'resource.not_found');
    }

    public function test_the_buyer_accepts_an_offer_and_confirms_a_completed_remedy(): void
    {
        [$buyer, $sellerOrder, $item] = $this->deliveredOrder();
        $case = $this->returnCase($sellerOrder, 'offered', 'refund');
        $case->items()->create(['order_item_id' => $item->id, 'quantity' => 1, 'unit_price' => $item->unit_price]);

        $this->actingAs($buyer)
            ->patchJson("/api/v1/returns/{$case->id}", ['action' => 'accept'])
            ->assertOk()
            ->assertJsonPath('data.status', 'awaiting_fulfillment');

        $confirmable = $this->returnCase($sellerOrder, 'awaiting_confirmation', 'refund');

        $this->actingAs($buyer)
            ->patchJson("/api/v1/returns/{$confirmable->id}", ['action' => 'confirm'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->actingAs(User::factory()->create())
            ->patchJson("/api/v1/returns/{$confirmable->id}", ['action' => 'confirm'])
            ->assertNotFound();
    }

    public function test_the_buyer_opens_one_support_request_per_store_order(): void
    {
        [$buyer, $sellerOrder] = $this->deliveredOrder();
        $payload = ['type' => 'delivery', 'details' => 'The parcel has not arrived yet.'];

        $this->actingAs($buyer)
            ->postJson("/api/v1/orders/seller-orders/{$sellerOrder->id}/issues", $payload)
            ->assertCreated()
            ->assertJsonPath('data.type', 'delivery')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.seller_order_id', $sellerOrder->id);

        $this->actingAs($buyer)
            ->postJson("/api/v1/orders/seller-orders/{$sellerOrder->id}/issues", $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error.fields.type.0', 'A request for this store order is already open.');

        $this->actingAs($buyer)
            ->getJson("/api/v1/orders/seller-orders/{$sellerOrder->id}/issues")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'delivery');
    }

    public function test_cancellation_is_refused_once_the_order_is_being_prepared(): void
    {
        [$buyer, $sellerOrder] = $this->deliveredOrder('processing');

        $this->actingAs($buyer)
            ->postJson("/api/v1/orders/seller-orders/{$sellerOrder->id}/issues", [
                'type' => 'cancellation',
                'details' => 'Please cancel this order before it ships.',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.fields.type.0', 'This order is already being prepared or delivered. Ask for order help instead.');

        $this->assertDatabaseCount('order_issues', 0);
    }

    /**
     * @return array{0: User, 1: SellerOrder, 2: OrderItem}
     */
    private function deliveredOrder(string $status = 'delivered'): array
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();
        $suffix = str()->lower(str()->random(8));
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
            'name' => 'Return Tote',
            'slug' => "return-tote-{$suffix}",
            'sku' => "RETURN-TOTE-{$suffix}",
            'price' => '199.00',
            'stock_quantity' => 5,
            'status' => 'active',
        ]);
        $order = Order::create([
            'user_id' => $buyer->id,
            'number' => 'JM-'.str()->upper(str()->random(8)),
            'status' => $status,
            'recipient_name' => $buyer->name,
            'phone' => '+639171234567',
            'address_line' => '100 Market Street',
            'city' => 'Manila',
            'subtotal' => '199.00',
            'shipping_amount' => '0.00',
            'total' => '199.00',
        ]);
        $order->payment()->create(['method' => 'cod', 'status' => 'paid', 'amount' => '199.00']);
        $sellerOrder = $order->sellerOrders()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'status' => $status,
            'subtotal' => '199.00',
            'shipping_amount' => '0.00',
            'total' => '199.00',
            'delivered_at' => $status === 'delivered' ? now() : null,
        ]);
        $item = $sellerOrder->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => '199.00',
            'quantity' => 1,
            'line_total' => '199.00',
        ]);

        return [$buyer, $sellerOrder, $item];
    }

    private function returnCase(SellerOrder $sellerOrder, string $status, string $remedy): ReturnCase
    {
        return ReturnCase::create([
            'seller_order_id' => $sellerOrder->id,
            'buyer_id' => $sellerOrder->order->user_id,
            'opened_by' => $sellerOrder->order->user_id,
            'status' => $status,
            'reason' => 'damaged',
            'requested_remedy' => $remedy,
            'remedy' => $remedy,
            'details' => 'The item arrived damaged.',
            'return_waived' => true,
            'buyer_respond_by' => now()->addDays(3),
        ]);
    }
}
