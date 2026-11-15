<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
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

    public function test_head_office_adds_a_branch_that_starts_closed(): void
    {
        [$tenant] = $this->pharmacy();
        $headOffice = $this->member($tenant, 'owner');
        $details = ['name' => 'Botika Plus Davao', 'phone' => '+639181234567', 'address_line' => '5 Roxas Avenue', 'city' => 'Davao'];

        $this->actingAs($headOffice)->post(route('seller.branches.store'), $details)->assertSessionHasNoErrors();

        $branch = $tenant->stores()->where('name', 'Botika Plus Davao')->sole();
        $this->assertSame('inactive', $branch->status);
        $this->actingAs($headOffice)->get(route('seller.branches.index'))->assertOk()->assertSee('Botika Plus Davao')->assertSee('No map pin yet');
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.opened', 'entity_id' => $branch->id, 'actor_user_id' => $headOffice->id]);

        $this->actingAs($headOffice)->post(route('seller.branches.store'), [...$details, 'name' => 'BOTIKA PLUS DAVAO'])
            ->assertSessionHasErrors('name');
        $this->assertSame(3, $tenant->stores()->count());
    }

    public function test_branch_staff_cannot_add_or_list_branches(): void
    {
        [$tenant, $makati] = $this->pharmacy();
        $branchManager = $this->member($tenant, 'manager', $makati);

        $this->actingAs($branchManager)->get(route('seller.branches.index'))->assertForbidden();
        $this->actingAs($branchManager)
            ->post(route('seller.branches.store'), ['name' => 'Rogue Branch', 'phone' => '1', 'address_line' => 'x', 'city' => 'y'])
            ->assertForbidden();
        $this->assertDatabaseMissing('stores', ['name' => 'Rogue Branch']);
    }

    public function test_head_office_opens_one_branch_but_cannot_touch_another_companys(): void
    {
        [$tenant, , $cebu] = $this->pharmacy();
        $headOffice = $this->member($tenant, 'manager');
        $cebu->update(['status' => 'inactive']);
        $otherBranch = $this->otherCompanyBranch();
        $details = ['phone' => '+639171234567', 'address_line' => '10 Rizal Street', 'city' => 'Cebu', 'status' => 'active'];

        $this->actingAs($headOffice)->patch(route('seller.store-settings.update', ['branch' => $cebu->id]), $details)
            ->assertRedirect(route('seller.store-settings.edit', ['branch' => $cebu->id]));
        $this->assertSame('active', $cebu->fresh()->status);

        $this->actingAs($headOffice)->get(route('seller.store-settings.edit', ['branch' => $otherBranch->id]))->assertNotFound();
        $this->actingAs($headOffice)->patch(route('seller.store-settings.update', ['branch' => $otherBranch->id]), [...$details, 'status' => 'inactive'])
            ->assertNotFound();
        $this->assertSame('active', $otherBranch->fresh()->status);
    }

    public function test_branch_staff_count_stock_only_at_their_own_branch(): void
    {
        [$tenant, $makati, $cebu] = $this->pharmacy();
        $product = $this->product($tenant, $makati, 'Paracetamol 500mg');
        $staff = $this->member($tenant, 'staff', $makati);

        // The branch in the request is ignored for branch staff: the count lands on their own branch.
        $this->actingAs($staff)
            ->patch(route('seller.branch-stock.update', ['branch' => $cebu->id]), ['stock' => [$product->id => ['default' => 40]]])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('branch_stocks', ['store_id' => $makati->id, 'product_id' => $product->id, 'variant_key' => 'default', 'quantity' => 40]);
        $this->assertDatabaseMissing('branch_stocks', ['store_id' => $cebu->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'branch_stock.counted', 'entity_id' => $makati->id, 'actor_user_id' => $staff->id]);

        $this->actingAs($staff)->get(route('seller.branch-stock.index'))
            ->assertOk()
            ->assertSee('Stock at Botika Plus Makati')
            ->assertSee('value="40"', false);
    }

    public function test_head_office_counts_stock_for_any_branch_but_only_its_own_products(): void
    {
        [$tenant, $makati, $cebu] = $this->pharmacy();
        $product = $this->product($tenant, $makati, 'Amoxicillin 500mg');
        $otherBranch = $this->otherCompanyBranch();
        $otherProduct = $this->product($otherBranch->tenant, $otherBranch, 'Foreign Syrup');
        $headOffice = $this->member($tenant, 'manager');

        $this->actingAs($headOffice)
            ->patch(route('seller.branch-stock.update', ['branch' => $cebu->id]), ['stock' => [$product->id => ['default' => 12]]])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('branch_stocks', ['store_id' => $cebu->id, 'product_id' => $product->id, 'quantity' => 12]);

        $this->actingAs($headOffice)
            ->patch(route('seller.branch-stock.update', ['branch' => $cebu->id]), ['stock' => [$otherProduct->id => ['default' => 5]]])
            ->assertSessionHasErrors('stock');
        $this->actingAs($headOffice)
            ->patch(route('seller.branch-stock.update', ['branch' => $cebu->id]), ['stock' => [$product->id => ['not-a-variant' => 5]]])
            ->assertSessionHasErrors('stock');
        $this->actingAs($headOffice)
            ->patch(route('seller.branch-stock.update', ['branch' => $cebu->id]), ['stock' => [$product->id => ['default' => -1]]])
            ->assertSessionHasErrors('stock.'.$product->id.'.default');
        $this->assertDatabaseMissing('branch_stocks', ['product_id' => $otherProduct->id]);
        $this->assertSame(12, $cebu->branchStocks()->sole()->quantity);
    }

    private function otherCompanyBranch(): Store
    {
        $tenant = Tenant::firstOrCreate(['slug' => 'rival-drug'], ['name' => 'Rival Drug', 'status' => 'active']);

        return Store::firstOrCreate(['slug' => 'rival-drug-manila'], [
            'tenant_id' => $tenant->id,
            'name' => 'Rival Drug Manila',
            'phone' => '+639171234567',
            'address_line' => '1 Taft Avenue',
            'city' => 'Manila',
            'status' => 'active',
        ]);
    }

    private function product(Tenant $tenant, Store $store, string $name): Product
    {
        return Product::create([
            'tenant_id' => $tenant->id,
            'store_id' => $store->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'sku' => strtoupper(str($name)->slug()),
            'price' => '25.00',
            'stock_quantity' => 0,
            'status' => 'active',
        ]);
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
