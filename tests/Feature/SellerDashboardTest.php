<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_seller_sees_store_scoped_dashboard_and_empty_states(): void
    {
        [$seller, $tenant, $store] = $this->seller();
        Product::create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'name' => 'Canvas Tote', 'slug' => 'canvas-tote', 'sku' => 'TOTE-001', 'price' => '299.00', 'stock_quantity' => 0, 'status' => 'active']);
        [, $otherTenant, $otherStore] = $this->seller();
        Product::create(['tenant_id' => $otherTenant->id, 'store_id' => $otherStore->id, 'name' => 'Private Item', 'slug' => 'private-item', 'sku' => 'PRIVATE-001', 'price' => '199.00', 'stock_quantity' => 5, 'status' => 'active']);

        $this->actingAs($seller)->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('Seller Center')
            ->assertSee('Northwind Goods')
            ->assertSee('Canvas Tote')
            ->assertDontSee('Private Item')
            ->assertSee('No orders yet')
            ->assertSee('Needs your attention')
            ->assertSee('Finish store setup')
            ->assertSee('data-seller-shortcut="search"', false)
            ->assertSee('Search seller help')
            ->assertSee('data-seller-help-search', false)
            ->assertSee('Reply to buyer messages')
            ->assertSee('Update store details')
            ->assertSee('All time · seller order totals; cancelled orders excluded')
            ->assertSee('Unique buyers')
            ->assertSee('Shortcuts: S products · N add product · O orders · ? help')
            ->assertSee('aria-keyshortcuts="S"', false)
            ->assertSee('aria-keyshortcuts="N"', false)
            ->assertSee('aria-keyshortcuts="O"', false)
            ->assertSee('Sales overview')
            ->assertSee('Sales trend for the last 30 days')
            ->assertSee('Quick actions')
            ->assertSee(route('catalog.store', $store->slug), false)
            ->assertSee('target="_blank"', false)
            ->assertSee("View {$store->name} storefront in a new tab");
    }

    public function test_seller_without_active_store_is_redirected_home(): void
    {
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);

        $this->actingAs($seller)->get(route('seller.dashboard'))
            ->assertRedirect(route('home'));
    }

    public function test_dashboard_query_failure_offers_a_safe_retry(): void
    {
        [$seller] = $this->seller();

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE seller_orders CASCADE');
        } else {
            Schema::drop('seller_orders');
        }

        DB::beginTransaction();

        try {
            $this->actingAs($seller)->get(route('seller.dashboard'))
                ->assertServiceUnavailable()
                ->assertSee('We couldn’t load your overview')
                ->assertSee('Your listings and orders haven’t been changed.')
                ->assertSee('Open products')
                ->assertSee(route('seller.products.index'), false)
                ->assertSee(route('seller.dashboard'), false)
                ->assertSee('Try again');
        } finally {
            // PostgreSQL aborts the whole transaction after a failed statement.
            // Rolling back to this savepoint lets the follow-up request run.
            DB::rollBack();
        }

        $this->actingAs($seller)->get(route('seller.products.index'))
            ->assertOk();
    }

    public function test_seller_summary_counts_only_its_own_non_cancelled_sales_and_customers(): void
    {
        [$seller, , $store] = $this->seller();
        [, , $otherStore] = $this->seller();
        $buyer = User::factory()->create();
        $otherBuyer = User::factory()->create();

        $this->createSellerOrder($store, $buyer, 'OWN-ORDER', 'delivered', 100);
        $this->createSellerOrder($store, $otherBuyer, 'CANCELLED-ORDER', 'cancelled', 200);
        $this->createSellerOrder($otherStore, $otherBuyer, 'OTHER-ORDER', 'delivered', 900);

        $this->actingAs($seller)->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('₱100.00')
            ->assertDontSee('₱900.00')
            ->assertViewHas('customerCount', 1)
            ->assertViewHas('totalOrders', 2)
            ->assertViewHas('salesHistory', fn ($history): bool => $history->count() === 30 && (float) $history->last()['amount'] === 100.0);
    }

    public function test_branch_pharmacist_sees_who_they_are_and_the_prescriptions_waiting_for_them(): void
    {
        [, $tenant, $headStore] = $this->seller();
        $branch = Store::create(['tenant_id' => $tenant->id, 'name' => 'Northwind Ermita', 'slug' => 'northwind-ermita-'.str()->lower(str()->random(6)), 'phone' => '+639171234567', 'address_line' => '1 Taft Avenue', 'city' => 'Manila', 'status' => 'active']);
        $pharmacist = User::factory()->seller()->create(['name' => 'Liza Garcia', 'email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $tenant->id, 'store_id' => $branch->id, 'user_id' => $pharmacist->id, 'role' => 'staff', 'is_pharmacist' => true, 'license_number' => '0123456', 'license_expires_on' => now()->addYear()->toDateString()]);
        $buyer = User::factory()->create();
        $this->createSellerOrder($branch, $buyer, 'RX-1001', 'pending', 250);
        Prescription::create(['order_id' => Order::where('number', 'RX-1001')->value('id'), 'user_id' => $buyer->id, 'status' => 'pending']);
        $this->createSellerOrder($branch, $buyer, 'RX-NEEDS-PHOTO', 'pending', 180);
        Prescription::create(['order_id' => Order::where('number', 'RX-NEEDS-PHOTO')->value('id'), 'user_id' => $buyer->id, 'status' => 'needs_photo']);
        $this->createSellerOrder($headStore, $buyer, 'RX-OTHER-BRANCH', 'pending', 300);
        Prescription::create(['order_id' => Order::where('number', 'RX-OTHER-BRANCH')->value('id'), 'user_id' => $buyer->id, 'status' => 'pending']);

        $this->actingAs($pharmacist)->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('Liza Garcia')
            ->assertSee('Ermita branch')
            ->assertSee('Pharmacist</strong> · PRC 0123456', false)
            ->assertSee('Prescription desk')
            ->assertSee('1 prescription waiting for you')
            ->assertSee('RX-1001')
            ->assertDontSee('RX-OTHER-BRANCH')
            ->assertViewHas('prescriptionQueue', fn ($queue): bool => $queue->map(fn ($sellerOrder) => $sellerOrder->order->number)->all() === ['RX-1001'])
            ->assertSee(route('seller.branch-stock.index'), false)
            ->assertSee(route('seller.orders.index', ['review' => 'prescription']), false)
            ->assertSee('View only')
            ->assertDontSee('Sales overview')
            ->assertDontSee(route('seller.staff.index'), false)
            ->assertDontSee(route('seller.branches.index'), false);
    }

    public function test_owner_without_a_license_is_told_why_they_cannot_review_prescriptions(): void
    {
        [$seller, $tenant] = $this->seller();
        TenantMember::where('tenant_id', $tenant->id)->update(['is_pharmacist' => true]);

        $this->actingAs($seller)->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('Owner')
            ->assertSee('Head office · all branches')
            ->assertSee('no PRC license on file')
            ->assertSee('Add your PRC license number and expiry date to review prescriptions.')
            ->assertDontSee('data-prescription-desk', false)
            ->assertSee(route('seller.staff.index'), false);
    }

    public function test_branch_manager_overview_is_titled_with_their_branch(): void
    {
        [, $tenant] = $this->seller();
        $branch = Store::create(['tenant_id' => $tenant->id, 'name' => 'Cebu Lahug', 'slug' => 'cebu-lahug-'.str()->lower(str()->random(6)), 'phone' => '+639171234567', 'address_line' => '2 Gorordo Avenue', 'city' => 'Cebu City', 'status' => 'active']);
        $manager = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $tenant->id, 'store_id' => $branch->id, 'user_id' => $manager->id, 'role' => 'manager']);

        $this->actingAs($manager)->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('<h1 class="sc-title">Cebu Lahug branch</h1>', false)
            ->assertSee('Branch manager')
            ->assertSee('Sales overview')
            ->assertSee(route('seller.opening-hours.edit'), false)
            ->assertDontSee(route('seller.branches.index'), false);
    }

    public function test_branch_manager_sees_city_when_the_branch_name_is_generic(): void
    {
        [, $tenant] = $this->seller();
        $branch = Store::create(['tenant_id' => $tenant->id, 'name' => 'Northwind Store', 'slug' => 'northwind-store-'.str()->lower(str()->random(6)), 'phone' => '+639171234567', 'address_line' => '2 Gorordo Avenue', 'city' => 'Cebu City', 'status' => 'active']);
        $manager = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $tenant->id, 'store_id' => $branch->id, 'user_id' => $manager->id, 'role' => 'manager']);

        $this->actingAs($manager)->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('<h1 class="sc-title">Cebu City branch</h1>', false)
            ->assertSee('Cebu City branch');
    }

    public function test_head_office_compares_branches_and_head_office_staff_get_no_stock_link(): void
    {
        [$owner, $tenant, $headStore] = $this->seller();
        $branch = Store::create(['tenant_id' => $tenant->id, 'name' => 'Northwind Ermita', 'slug' => 'northwind-ermita-'.str()->lower(str()->random(6)), 'phone' => '+639171234567', 'address_line' => '1 Taft Avenue', 'city' => 'Manila', 'status' => 'active']);
        $buyer = User::factory()->create();
        $this->createSellerOrder($branch, $buyer, 'BRANCH-1001', 'pending', 250);
        Prescription::create(['order_id' => Order::where('number', 'BRANCH-1001')->value('id'), 'user_id' => $buyer->id, 'status' => 'pending']);

        $this->actingAs($owner)->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('Branches today')
            ->assertViewHas('branches', fn ($branches): bool => $branches->count() === 2
                && $branches->firstWhere('id', $branch->id)->pending_orders_count === 1
                && $branches->firstWhere('id', $branch->id)->prescriptions_waiting_count === 1
                && $branches->firstWhere('id', $headStore->id)->pending_orders_count === 0);

        $headOfficeStaff = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $headOfficeStaff->id, 'role' => 'staff']);

        $this->actingAs($headOfficeStaff)->get(route('seller.dashboard'))
            ->assertOk()
            ->assertDontSee('Branches today')
            ->assertDontSee(route('seller.branch-stock.index'), false);
    }

    public function test_owner_is_warned_about_branches_without_a_pharmacist_and_expiring_licenses(): void
    {
        [$owner, $tenant, $headStore] = $this->seller();
        $branch = Store::create(['tenant_id' => $tenant->id, 'name' => 'Northwind Ermita', 'slug' => 'northwind-ermita-'.str()->lower(str()->random(6)), 'phone' => '+639171234567', 'address_line' => '1 Taft Avenue', 'city' => 'Manila', 'status' => 'active']);
        $pharmacist = User::factory()->seller()->create(['name' => 'Liza Garcia']);
        TenantMember::create(['tenant_id' => $tenant->id, 'store_id' => $branch->id, 'user_id' => $pharmacist->id, 'role' => 'staff', 'is_pharmacist' => true, 'license_number' => '0123456', 'license_expires_on' => today()->addDays(10)->toDateString()]);

        $this->actingAs($owner)->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('No pharmacist with a valid license at '.$headStore->name.'.')
            ->assertDontSee('at '.$branch->name.'.')
            ->assertSee('Liza Garcia on '.today()->addDays(10)->format('M j'));

        $headOfficePharmacist = User::factory()->seller()->create();
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $headOfficePharmacist->id, 'role' => 'manager', 'is_pharmacist' => true, 'license_number' => '7654321', 'license_expires_on' => today()->addYear()->toDateString()]);

        // A head-office pharmacist can review every branch, so no branch is left uncovered.
        $this->actingAs($owner)->get(route('seller.dashboard'))
            ->assertOk()
            ->assertDontSee('data-pharmacist-coverage', false);
    }

    public function test_license_status_warns_a_month_before_expiry(): void
    {
        $member = new TenantMember(['is_pharmacist' => true, 'license_number' => '0123456']);

        $this->assertSame('valid', $member->fill(['license_expires_on' => today()->addDays(30)])->licenseStatus());
        $this->assertSame('expiring', $member->fill(['license_expires_on' => today()->addDays(29)])->licenseStatus());
        $this->assertSame('expiring', $member->fill(['license_expires_on' => today()])->licenseStatus());
        $this->assertSame('expired', $member->fill(['license_expires_on' => today()->subDay()])->licenseStatus());
        $this->assertSame('missing', $member->fill(['license_number' => null])->licenseStatus());
        $this->assertNull($member->fill(['is_pharmacist' => false])->licenseStatus());
    }

    public function test_dashboard_reuses_role_and_product_counts(): void
    {
        [$seller, $tenant, $store] = $this->seller();
        Product::create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'name' => 'Canvas Tote', 'slug' => 'canvas-tote', 'sku' => 'TOTE-001', 'price' => '299.00', 'stock_quantity' => 1, 'status' => 'active']);
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->actingAs($seller)->get(route('seller.dashboard'))
            ->assertOk()
            ->assertViewHas('totalProducts', 1)
            ->assertViewHas('canManageProducts', true)
            ->assertViewHas('canManageOrders', true);

        $roleQueries = array_filter($queries, fn (string $sql): bool => str_starts_with($sql, 'select exists(select * from "tenant_members"') && str_contains($sql, 'role'));
        $this->assertLessThanOrEqual(3, count($roleQueries));
        $this->assertCount(0, array_filter($queries, fn (string $sql): bool => str_starts_with($sql, 'select exists(select * from "products"')));
    }

    private function createSellerOrder(Store $store, User $buyer, string $number, string $status, int $total): void
    {
        $order = Order::create([
            'user_id' => $buyer->id,
            'number' => $number,
            'recipient_name' => $buyer->name,
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'subtotal' => $total,
            'shipping_amount' => 0,
            'total' => $total,
            'status' => $status,
        ]);
        $order->sellerOrders()->create([
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
            'subtotal' => $total,
            'shipping_amount' => 0,
            'total' => $total,
            'status' => $status,
        ]);
    }

    /**
     * @return array{0: User, 1: Tenant, 2: Store}
     */
    private function seller(): array
    {
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        $tenant = Tenant::create(['name' => 'Northwind', 'slug' => 'northwind-'.str()->lower(str()->random(6)), 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => 'Northwind Goods '.str()->random(6), 'slug' => 'northwind-goods-'.str()->lower(str()->random(6)), 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $seller->id, 'role' => 'owner']);

        return [$seller, $tenant, $store];
    }
}
