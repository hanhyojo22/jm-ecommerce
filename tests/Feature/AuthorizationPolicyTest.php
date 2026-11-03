<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Conversation;
use App\Models\DeliveryAddress;
use App\Models\MessageAttachment;
use App\Models\Order;
use App\Models\OrderIssue;
use App\Models\Product;
use App\Models\ReturnCase;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_and_store_order_policies_separate_buyers_members_and_admins(): void
    {
        [$buyer, $order, $sellerOrder, $tenant] = $this->purchase();
        $otherBuyer = User::factory()->create();
        $member = $this->member($tenant, 'staff');
        $outsider = $this->member($this->tenant(), 'owner');
        $admin = User::factory()->administrator()->create();

        $this->assertTrue($buyer->can('viewAny', Order::class));
        $this->assertTrue($buyer->can('view', $order));
        $this->assertTrue($buyer->can('pay', $order));
        $this->assertTrue($admin->can('view', $order));
        $this->assertFalse($otherBuyer->can('view', $order));
        $this->assertFalse($otherBuyer->can('pay', $order));

        $this->assertTrue($buyer->can('receive', $sellerOrder));
        $this->assertTrue($buyer->can('openIssue', $sellerOrder));
        $this->assertTrue($buyer->can('openReturn', $sellerOrder));
        $this->assertTrue($member->can('view', $sellerOrder));
        $this->assertTrue($admin->can('view', $sellerOrder));
        $this->assertFalse($otherBuyer->can('receive', $sellerOrder));
        $this->assertFalse($otherBuyer->can('openIssue', $sellerOrder));
        $this->assertFalse($otherBuyer->can('openReturn', $sellerOrder));
        $this->assertFalse($outsider->can('view', $sellerOrder));
    }

    public function test_an_inactive_member_or_inactive_buyer_loses_access(): void
    {
        [$buyer, $order, $sellerOrder, $tenant] = $this->purchase();
        $inactiveMember = $this->member($tenant, 'owner');
        $inactiveMember->forceFill(['is_active' => false])->save();

        $buyer->forceFill(['is_active' => false])->save();

        $this->assertFalse($inactiveMember->can('view', $sellerOrder));
        $this->assertFalse($buyer->can('view', $order));
        $this->assertFalse($buyer->can('receive', $sellerOrder));
    }

    public function test_return_case_and_order_issue_policies_follow_the_order_participants(): void
    {
        [$buyer, $order, $sellerOrder, $tenant] = $this->purchase();
        $otherBuyer = User::factory()->create();
        $member = $this->member($tenant, 'owner');
        $admin = User::factory()->administrator()->create();
        $case = ReturnCase::create([
            'seller_order_id' => $sellerOrder->id,
            'buyer_id' => $buyer->id,
            'opened_by' => $buyer->id,
            'status' => 'awaiting_seller',
            'reason' => 'damaged',
            'requested_remedy' => 'refund',
            'details' => 'The item arrived with a cracked body.',
        ]);
        $issue = OrderIssue::create([
            'seller_order_id' => $sellerOrder->id,
            'buyer_id' => $buyer->id,
            'type' => 'delivery',
            'details' => 'The parcel has not arrived yet.',
        ]);

        $this->assertTrue($buyer->can('view', $case));
        $this->assertTrue($buyer->can('respond', $case));
        $this->assertTrue($member->can('view', $case));
        $this->assertTrue($admin->can('view', $case));
        $this->assertFalse($member->can('respond', $case));
        $this->assertFalse($otherBuyer->can('view', $case));
        $this->assertFalse($otherBuyer->can('respond', $case));

        $this->assertTrue($buyer->can('view', $issue));
        $this->assertTrue($member->can('view', $issue));
        $this->assertTrue($admin->can('view', $issue));
        $this->assertFalse($otherBuyer->can('view', $issue));

        $this->actingAs($otherBuyer)->get(route('returns.show', $case))->assertNotFound();
        $this->actingAs($otherBuyer)->get(route('orders.pay', $order))->assertNotFound();
    }

    public function test_address_cart_and_attachment_policies_are_owner_or_participant_only(): void
    {
        [$buyer, , , $tenant, $store] = $this->purchase();
        $otherBuyer = User::factory()->create();
        $member = $this->member($tenant, 'manager');

        $address = $buyer->deliveryAddresses()->create([
            'recipient_name' => 'Ava Buyer',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'postal_code' => '1000',
        ]);
        $cartItem = CartItem::create([
            'cart_id' => Cart::create(['user_id' => $buyer->id])->id,
            'product_id' => Product::query()->where('store_id', $store->id)->sole()->id,
            'variant_key' => 'default',
            'quantity' => 1,
        ]);
        $conversation = Conversation::create([
            'buyer_id' => $buyer->id,
            'store_id' => $store->id,
            'product_id' => $cartItem->product_id,
        ]);
        $message = $conversation->messages()->create([
            'sender_id' => $buyer->id,
            'client_message_id' => (string) str()->uuid(),
            'type' => 'file',
            'body' => '',
        ]);
        $attachment = MessageAttachment::create([
            'conversation_message_id' => $message->id,
            'uploaded_by' => $buyer->id,
            'disk' => 'chat_attachments',
            'path' => 'chat/receipt.pdf',
            'original_name' => 'receipt.pdf',
            'mime_type' => 'application/pdf',
            'byte_size' => 100,
        ]);

        $this->assertTrue($buyer->can('viewAny', DeliveryAddress::class));
        $this->assertTrue($buyer->can('create', DeliveryAddress::class));
        $this->assertTrue($buyer->can('view', $address));
        $this->assertTrue($buyer->can('update', $address));
        $this->assertTrue($buyer->can('delete', $address));
        $this->assertFalse($otherBuyer->can('view', $address));
        $this->assertFalse($otherBuyer->can('update', $address));
        $this->assertFalse($otherBuyer->can('delete', $address));

        $this->assertTrue($buyer->can('update', $cartItem));
        $this->assertTrue($buyer->can('delete', $cartItem));
        $this->assertFalse($otherBuyer->can('update', $cartItem));
        $this->assertFalse($otherBuyer->can('delete', $cartItem));

        $this->assertTrue($buyer->can('view', $attachment));
        $this->assertTrue($member->can('view', $attachment));
        $this->assertFalse($otherBuyer->can('view', $attachment));
    }

    /**
     * @return array{0: User, 1: Order, 2: SellerOrder, 3: Tenant, 4: Store}
     */
    private function purchase(): array
    {
        $buyer = User::factory()->create();
        $tenant = $this->tenant();
        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'Policy Store',
            'slug' => 'policy-store',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => 'active',
        ]);
        Product::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => 'Canvas Tote',
            'slug' => 'policy-canvas-tote',
            'sku' => 'POLICY-TOTE',
            'price' => '500.00',
            'stock_quantity' => 5,
            'status' => 'active',
        ]);
        $order = Order::create([
            'user_id' => $buyer->id,
            'number' => 'POLICY-1001',
            'recipient_name' => 'Ava Buyer',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'subtotal' => '500.00',
            'shipping_amount' => '0.00',
            'total' => '500.00',
        ]);
        $order->payment()->create(['method' => 'cod', 'status' => 'pending', 'amount' => '500.00']);
        $sellerOrder = $order->sellerOrders()->create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'subtotal' => '500.00',
            'shipping_amount' => '0.00',
            'total' => '500.00',
        ]);

        return [$buyer, $order, $sellerOrder, $tenant, $store];
    }

    private function tenant(): Tenant
    {
        $suffix = str()->lower(str()->random(6));

        return Tenant::create([
            'name' => "Tenant {$suffix}",
            'slug' => "tenant-{$suffix}",
            'status' => 'active',
        ]);
    }

    private function member(Tenant $tenant, string $role): User
    {
        $member = User::factory()->seller()->create();
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $member->id, 'role' => $role]);

        return $member;
    }
}
