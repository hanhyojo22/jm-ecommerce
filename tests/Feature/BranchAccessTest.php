<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Prescription;
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

    public function test_branch_staff_move_orders_forward_but_only_managers_cancel(): void
    {
        [$tenant, $makati] = $this->pharmacy();
        $order = $this->sellerOrder($makati, 'MAKATI-2001');
        $staff = $this->member($tenant, 'staff', $makati);

        $this->actingAs($staff)->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertViewHas('sellerOrder', fn ($sellerOrder): bool => $sellerOrder->available_transitions === ['confirmed']);

        $this->actingAs($staff)
            ->patch(route('seller.orders.update', $order), ['status' => 'cancelled'])
            ->assertForbidden();
        $this->actingAs($staff)
            ->delete(route('seller.orders.senior-pwd-discount.destroy', $order))
            ->assertForbidden();
        $this->assertDatabaseHas('seller_orders', ['id' => $order->id, 'status' => 'pending']);

        $this->actingAs($staff)
            ->patch(route('seller.orders.update', $order), ['status' => 'confirmed'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('seller_orders', ['id' => $order->id, 'status' => 'confirmed']);
    }

    public function test_head_office_staff_only_read_order_documents(): void
    {
        [$tenant, $makati] = $this->pharmacy();
        $order = $this->sellerOrder($makati, 'MAKATI-3001');
        Prescription::create(['order_id' => $order->order_id, 'user_id' => $order->order->user_id, 'status' => 'pending']);
        $order->forceFill(['discount_amount' => '20.00'])->save();
        $order->order->forceFill(['discount_card_type' => 'senior', 'discount_card_name' => 'Cardholder', 'discount_document_path' => 'private-id.jpg', 'discount_document_mime' => 'image/jpeg'])->save();
        $headOfficeStaff = $this->member($tenant, 'staff');

        $this->actingAs($headOfficeStaff)->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Approve ID')
            ->assertDontSee('Mark as checked')
            ->assertDontSee('Ask for a clearer photo')
            ->assertDontSee('Original ID checked</button>', false);
        $this->actingAs($headOfficeStaff)->post(route('seller.orders.discount-document.review', $order))->assertForbidden();
        $this->post(route('seller.orders.discount-card.check', $order))->assertForbidden();
        $this->actingAs($headOfficeStaff)->post(route('seller.orders.prescription.check', $order))->assertForbidden();
        $this->actingAs($headOfficeStaff)
            ->patch(route('seller.orders.prescription.update', $order), ['decision' => 'needs_photo', 'reason' => 'The photo is blurry.'])
            ->assertForbidden();

        $this->assertDatabaseHas('prescriptions', ['order_id' => $order->order_id, 'status' => 'pending', 'checked_at' => null]);
        $this->assertDatabaseHas('seller_orders', ['id' => $order->id, 'discount_reviewed_at' => null]);
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

    public function test_only_head_office_manages_medicines_and_a_branch_manager_edits_only_their_branch(): void
    {
        [$tenant, $makati, $cebu] = $this->pharmacy();
        $branchManager = $this->member($tenant, 'manager', $makati);
        $headOffice = $this->member($tenant, 'manager');
        $branchStaff = $this->member($tenant, 'staff', $makati);

        $this->actingAs($branchManager)->get(route('seller.products.create'))->assertForbidden();
        $this->actingAs($branchStaff)->get(route('seller.store-settings.edit'))->assertForbidden();
        // The branch in the request is ignored: a branch manager always gets their own branch, and cannot close it.
        $this->actingAs($branchManager)->get(route('seller.store-settings.edit', ['branch' => $cebu->id]))
            ->assertOk()
            ->assertViewHas('store', fn ($store): bool => $store->is($makati))
            ->assertDontSee('Branch status');
        $this->actingAs($branchManager)
            ->patch(route('seller.store-settings.update', ['branch' => $cebu->id]), ['phone' => '+639179998888', 'address_line' => '5 Ayala Avenue', 'city' => 'Makati', 'status' => 'inactive'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stores', ['id' => $makati->id, 'phone' => '+639179998888', 'status' => 'active']);
        $this->assertDatabaseMissing('stores', ['id' => $cebu->id, 'phone' => '+639179998888']);

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

    public function test_branch_directory_shows_today_map_attention_and_suspension(): void
    {
        [$tenant, $makati, $cebu] = $this->pharmacy();
        $headOffice = $this->member($tenant, 'owner');
        $this->travelTo(now('Asia/Manila')->setTime(21, 30));
        $makati->update(['opening_time' => '08:00', 'closing_time' => '21:00', 'latitude' => 14.55, 'longitude' => 121.02, 'delivery_radius_km' => 8]);
        $cebu->update(['status' => 'suspended']);

        $this->actingAs($headOffice)->get(route('seller.branches.index'))
            ->assertOk()
            ->assertSeeInOrder(['Botika Plus Makati', 'Closed now', 'Opens tomorrow, 8:00 AM', 'Pinned · 8 km', 'Close branch'])
            ->assertSee('<strong>Botika Plus Cebu</strong> <span>has no map pin', false)
            ->assertSeeInOrder(['Botika Plus Cebu', 'Suspended by the platform', 'No map pin yet', 'Only the platform can lift a suspension'])
            ->assertDontSee('Open branch');
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

    public function test_only_head_office_renames_a_branch_and_its_url_stays(): void
    {
        [$tenant, $makati] = $this->pharmacy();
        $slug = $makati->slug;
        $details = ['phone' => '+639171234567', 'address_line' => '10 Rizal Street', 'city' => 'Makati'];

        $this->actingAs($this->member($tenant, 'manager', $makati))
            ->patch(route('seller.store-settings.update'), [...$details, 'name' => 'Renamed by branch'])
            ->assertSessionHasNoErrors();
        $this->assertNotSame('Renamed by branch', $makati->fresh()->name);

        $headOffice = $this->member($tenant, 'owner');
        $this->actingAs($headOffice)->get(route('seller.store-settings.edit', ['branch' => $makati->id]))->assertSee('Branch name');
        $this->actingAs($headOffice)->patch(route('seller.store-settings.update', ['branch' => $makati->id]), [...$details, 'name' => ''])
            ->assertSessionHasErrors('name');
        $this->actingAs($headOffice)->patch(route('seller.store-settings.update', ['branch' => $makati->id]), [...$details, 'name' => 'Everyday Makati Ayala'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Everyday Makati Ayala', $slug], [$makati->fresh()->name, $makati->fresh()->slug]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.updated', 'entity_id' => $makati->id]);
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

    public function test_head_office_cannot_reopen_a_branch_the_platform_suspended(): void
    {
        [$tenant, $makati] = $this->pharmacy();
        $makati->update(['status' => 'suspended']);

        $this->actingAs($this->member($tenant, 'owner'))
            ->patch(route('seller.store-settings.update', ['branch' => $makati->id]), ['phone' => '+639170000000', 'address_line' => '10 Rizal Street', 'city' => 'Makati', 'status' => 'active'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['suspended', '+639170000000'], [$makati->fresh()->status, $makati->fresh()->phone]);
    }

    public function test_order_alerts_go_to_head_office_and_the_orders_branch_only(): void
    {
        [$tenant, $makati, $cebu] = $this->pharmacy();
        $headOffice = $this->member($tenant, 'owner');
        $makatiStaff = $this->member($tenant, 'staff', $makati);
        $cebuStaff = $this->member($tenant, 'staff', $cebu);

        $this->assertEqualsCanonicalizing([$headOffice->id, $makatiStaff->id], $tenant->activeNotifiableStaff($makati->id)->pluck('id')->all());

        // Closing the first branch must not silence alerts for the others.
        $makati->update(['status' => 'inactive']);
        $this->assertEqualsCanonicalizing([$headOffice->id, $cebuStaff->id], $tenant->fresh()->activeNotifiableStaff($cebu->id)->pluck('id')->all());

        // Staff of the closed branch stop hearing about its orders; head office still does.
        $this->assertSame([$headOffice->id], $tenant->fresh()->activeNotifiableStaff($makati->id)->pluck('id')->all());
    }

    public function test_branch_staff_cannot_authorize_another_branchs_order_records(): void
    {
        [$tenant, $makati, $cebu] = $this->pharmacy();
        $makatiOrder = $this->sellerOrder($makati, 'MAKATI-2001');
        $cebuStaff = $this->member($tenant, 'manager', $cebu);
        $headOffice = $this->member($tenant, 'manager');

        $this->assertFalse($cebuStaff->can('view', $makatiOrder));
        $this->assertTrue($headOffice->can('view', $makatiOrder));
        $this->assertTrue($this->member($tenant, 'staff', $makati)->can('view', $makatiOrder));
    }

    public function test_the_products_list_shows_branch_staff_their_own_branchs_stock(): void
    {
        [$tenant, $makati, $cebu] = $this->pharmacy();
        $product = $this->product($tenant, $makati, 'Paracetamol 500mg');
        $product->update(['stock_quantity' => 43]);
        $product->branchStocks()->createMany([
            ['store_id' => $makati->id, 'variant_key' => 'default', 'quantity' => 40],
            ['store_id' => $cebu->id, 'variant_key' => 'default', 'quantity' => 3],
        ]);

        $this->actingAs($this->member($tenant, 'manager', $cebu))->get(route('seller.products.index'))
            ->assertOk()
            ->assertSee('Stock at Botika Plus Cebu')
            ->assertSee('<span class="sc-num">3</span>', false)
            ->assertDontSee('<span class="sc-num">43</span>', false);
        $this->actingAs($this->member($tenant, 'owner'))->get(route('seller.products.index'))
            ->assertSee('<span class="sc-num">43</span>', false);
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

    public function test_branch_stock_is_consistent_on_dashboard_and_product_details(): void
    {
        [$tenant, $makati, $cebu] = $this->pharmacy();
        $product = $this->product($tenant, $makati, 'Paracetamol');
        $product->update(['stock_quantity' => 686]);
        $cebu->branchStocks()->create(['product_id' => $product->id, 'variant_key' => 'default', 'quantity' => 156]);
        $manager = $this->member($tenant, 'manager', $cebu);

        $this->actingAs($manager)->get(route('seller.dashboard'))
            ->assertOk()->assertSee('156 in stock')->assertDontSee('686 in stock');
        $this->get(route('seller.products.show', $product))
            ->assertOk()->assertSee('Stock at Botika Plus Cebu')->assertSee('156 in stock')->assertDontSee('686 in stock');
        $this->actingAs($this->member($tenant, 'manager'))->get(route('seller.products.show', $product))
            ->assertOk()->assertSee('Company inventory')->assertSee('686 in stock');
    }

    public function test_only_the_fulfilling_branch_can_see_the_delivery_pin(): void
    {
        [$tenant, $makati, $cebu] = $this->pharmacy();
        $order = $this->sellerOrder($cebu, 'CEBU-PIN');
        $order->order->update(['delivery_latitude' => 10.316, 'delivery_longitude' => 123.8912]);

        $this->actingAs($this->member($tenant, 'manager', $cebu))->get(route('seller.orders.show', $order))
            ->assertOk()->assertSee('Open delivery pin in Maps')->assertSee('destination=10.316%2C123.8912', false)
            ->assertSee('map_action=pano&amp;viewpoint=10.316%2C123.8912', false);
        $this->get(route('seller.orders.show', $order), ['X-Order-Modal' => '1'])
            ->assertOk()->assertSee('Open delivery pin in Maps');
        $this->actingAs($this->member($tenant, 'manager', $makati))->get(route('seller.orders.show', $order))
            ->assertNotFound()->assertDontSee('destination=');
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
