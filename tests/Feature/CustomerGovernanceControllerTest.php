<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerGovernanceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_filter_sort_and_view_customer_metrics(): void
    {
        $administrator = User::factory()->administrator()->create();
        $customer = User::factory()->create(['name' => 'Amelia Mercado', 'email' => 'amelia@example.com', 'created_at' => now()->subDays(10)]);
        $inactiveCustomer = User::factory()->unverified()->create(['name' => 'Brian Buyer', 'email' => 'brian@example.com', 'is_active' => false, 'created_at' => now()->subDays(120)]);
        $this->orderFor($customer, 'JM-PAID-001', 'delivered', 'paid', '120.00');
        $this->orderFor($customer, 'JM-REFUND-001', 'delivered', 'refunded', '45.00');
        $this->orderFor($customer, 'JM-CANCELLED-001', 'cancelled', 'paid', '35.00');

        $this->actingAs($administrator)->get(route('admin.customers.index', ['search' => 'amelia', 'status' => 'active', 'verification' => 'verified', 'registered' => 'last_30_days', 'sort' => 'name']))
            ->assertSee('Amelia Mercado')
            ->assertSee('₱120.00')
            ->assertSee('3')
            ->assertDontSee($inactiveCustomer->email);
    }

    public function test_admin_can_view_only_buyer_customer_details_and_order_history(): void
    {
        $administrator = User::factory()->administrator()->create();
        $customer = User::factory()->create(['name' => 'Casey Customer', 'email' => 'casey@example.com']);
        $order = $this->orderFor($customer, 'JM-DETAIL-001', 'delivered', 'paid', '88.00');
        $seller = User::factory()->seller()->create();

        $this->actingAs($administrator)->get(route('admin.customers.show', $customer))
            ->assertSee('Casey Customer')
            ->assertSee($order->number)
            ->assertSee('₱88.00');

        $this->actingAs($administrator)->get(route('admin.customers.show', $seller))->assertNotFound();
    }

    public function test_customer_pagination_keeps_selected_filters(): void
    {
        $administrator = User::factory()->administrator()->create();

        for ($number = 1; $number <= 16; $number++) {
            User::factory()->create(['name' => sprintf('Customer %02d', $number)]);
        }

        $this->actingAs($administrator)->get(route('admin.customers.index', ['status' => 'active', 'sort' => 'name', 'page' => 2]))
            ->assertSee('Customer 16')
            ->assertDontSee('Customer 01')
            ->assertSee('status=active', false)
            ->assertSee('sort=name', false);
    }

    public function test_non_administrators_cannot_access_customer_governance(): void
    {
        $customer = User::factory()->create();
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();

        $this->actingAs($buyer)->get(route('admin.customers.index'))->assertForbidden();
        $this->actingAs($seller)->get(route('admin.customers.show', $customer))->assertForbidden();
    }

    private function orderFor(User $customer, string $number, string $status, string $paymentStatus, string $total): Order
    {
        $order = Order::query()->create(['user_id' => $customer->id, 'number' => $number, 'status' => $status, 'recipient_name' => $customer->name, 'phone' => '+639171234567', 'address_line' => '100 Market Street', 'city' => 'Manila', 'subtotal' => $total, 'shipping_amount' => '0.00', 'total' => $total]);
        $order->payment()->create(['method' => 'cod', 'status' => $paymentStatus, 'amount' => $total]);

        return $order;
    }
}
