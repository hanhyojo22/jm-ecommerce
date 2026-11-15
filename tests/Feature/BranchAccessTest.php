<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_staff_see_only_their_own_branch_orders(): void
    {
        [$tenant, $makati, $cebu] = $this->pharmacy();
        $makatiOrder = $this->sellerOrder($makati, 'MAKATI-1001');
        $cebuOrder = $this->sellerOrder($cebu, 'CEBU-1001');
        $makatiStaff = $this->member($tenant, 'manager', $makati);

        $this->actingAs($makatiStaff)->get(route('seller.orders.index'))
            ->assertOk()
            ->assertSee('MAKATI-1001')
            ->assertDontSee('CEBU-1001');

        $this->actingAs($makatiStaff)->get(route('seller.orders.show', $cebuOrder))->assertNotFound();
        $this->actingAs($makatiStaff)
            ->patch(route('seller.orders.update', $cebuOrder), ['status' => 'confirmed'])
            ->assertNotFound();
        $this->assertDatabaseHas('seller_orders', ['id' => $cebuOrder->id, 'status' => 'pending']);
        $this->actingAs($makatiStaff)->get(route('seller.orders.show', $makatiOrder))->assertOk();
    }

    public function test_head_office_sees_every_branch_order(): void
    {
        [$tenant, $makati, $cebu] = $this->pharmacy();
        $this->sellerOrder($makati, 'MAKATI-1001');
        $this->sellerOrder($cebu, 'CEBU-1001');

        $this->actingAs($this->member($tenant, 'manager'))->get(route('seller.orders.index'))
            ->assertOk()
            ->assertSee('MAKATI-1001')
            ->assertSee('CEBU-1001');
    }

    public function test_only_head_office_manages_medicines_and_store_settings(): void
    {
        [$tenant, $makati] = $this->pharmacy();
        $branchManager = $this->member($tenant, 'manager', $makati);
        $headOffice = $this->member($tenant, 'manager');

        $this->actingAs($branchManager)->get(route('seller.products.create'))->assertForbidden();
        $this->actingAs($branchManager)->get(route('seller.store-settings.edit'))->assertForbidden();

        $this->actingAs($headOffice)->get(route('seller.products.create'))->assertOk();
        $this->actingAs($headOffice)->get(route('seller.store-settings.edit'))->assertOk();
    }

    public function test_staff_of_a_switched_off_branch_lose_the_workspace(): void
    {
        [$tenant, $makati] = $this->pharmacy();
        $staff = $this->member($tenant, 'staff', $makati);
        $makati->update(['status' => 'suspended']);

        $this->actingAs($staff)->get(route('seller.dashboard'))->assertRedirect(route('home'));
    }

    /** @return array{0: Tenant, 1: Store, 2: Store} a pharmacy with two active branches */
    private function pharmacy(): array
    {
        $tenant = Tenant::create(['name' => 'Botika Plus', 'slug' => 'botika-plus', 'status' => 'active']);
        $branch = fn (string $name): Store => Store::create([
            'tenant_id' => $tenant->id,
            'name' => "Botika Plus {$name}",
            'slug' => 'botika-plus-'.str($name)->slug(),
            'phone' => '+639171234567',
            'address_line' => '10 Rizal Street',
            'city' => $name,
            'status' => 'active',
        ]);

        return [$tenant, $branch('Makati'), $branch('Cebu')];
    }

    private function member(Tenant $tenant, string $role, ?Store $branch = null): User
    {
        $user = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $tenant->id, 'store_id' => $branch?->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function sellerOrder(Store $store, string $number): SellerOrder
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'number' => $number,
            'status' => 'pending',
            'recipient_name' => 'Ana Buyer',
            'phone' => '+639171234567',
            'address_line' => '1 Mabini Street',
            'city' => $store->city,
            'subtotal' => '100.00',
            'shipping_amount' => '0.00',
            'total' => '100.00',
        ]);

        return $order->sellerOrders()->create([
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
            'status' => 'pending',
            'subtotal' => '100.00',
            'shipping_amount' => '0.00',
            'total' => '100.00',
        ]);
    }
}
