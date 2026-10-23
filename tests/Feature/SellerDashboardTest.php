<?php

namespace Tests\Feature;

use App\Models\Order;
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
            ->assertSee('Search your products by name or SKU')
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
            ->assertSee('View Northwind Goods storefront in a new tab');
    }

    public function test_seller_without_active_store_is_redirected_to_application_onboarding(): void
    {
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);

        $this->actingAs($seller)->get(route('seller.dashboard'))
            ->assertRedirect(route('seller-application.create'));
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
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => 'Northwind Goods', 'slug' => 'northwind-goods-'.str()->lower(str()->random(6)), 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $seller->id, 'role' => 'owner']);

        return [$seller, $tenant, $store];
    }
}
