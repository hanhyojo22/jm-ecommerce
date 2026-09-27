<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerApplication;
use App\Models\SellerOrder;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_search_filter_sort_and_paginate_stores(): void
    {
        $administrator = User::factory()->administrator()->create();
        $category = Category::create(['name' => 'Fashion', 'slug' => 'fashion', 'is_active' => true]);
        $matching = $this->store('Northwind Goods', 'active');
        $this->attachOwner($matching, 'Ava Seller', 'ava@example.com');
        $this->product($matching, 'Canvas Tote', 'TOTE-001', 'active', $category->id);
        $other = $this->store('Harbor Market', 'suspended');
        $this->attachOwner($other, 'Ben Seller', 'ben@example.com');

        $this->actingAs($administrator)->get(route('admin.stores.index', [
            'search' => 'ava@example.com',
            'status' => 'active',
            'category_id' => $category->id,
            'joined' => 'last_30_days',
            'sort' => 'name',
        ]))
            ->assertOk()
            ->assertSee('Northwind Goods')
            ->assertSee('Ava Seller')
            ->assertDontSee('Harbor Market');
    }

    public function test_store_pagination_keeps_selected_filters(): void
    {
        $administrator = User::factory()->administrator()->create();

        for ($number = 1; $number <= 16; $number++) {
            $this->store(sprintf('Store %02d', $number), 'active');
        }

        $this->actingAs($administrator)->get(route('admin.stores.index', ['status' => 'active', 'sort' => 'name', 'page' => 2]))
            ->assertOk()
            ->assertSee('Store 16')
            ->assertDontSee('Store 01')
            ->assertSee('status=active', false)
            ->assertSee('sort=name', false);
    }

    public function test_store_overview_keeps_pending_applications_separate_from_store_cards_and_counts(): void
    {
        $administrator = User::factory()->administrator()->create();
        $store = $this->store('JM', 'active');
        $this->attachOwner($store, 'Franil Escalicas', 'franil@example.com');
        $seller = User::factory()->seller()->create();
        $seller->sellerApplications()->create([
            'store_name' => 'Pending seller shop',
            'seller_name' => 'Pending Seller',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $this->actingAs($administrator)->get(route('admin.stores.index'))
            ->assertOk()
            ->assertSee('Stores overview')
            ->assertSee('1 seller application awaiting review')
            ->assertSee('Review application')
            ->assertSee('JM')
            ->assertSee('Franil Escalicas')
            ->assertSee('Total stores')
            ->assertSee('Active')
            ->assertSee('Inactive')
            ->assertSee('Suspended')
            ->assertSee('View details')
            ->assertDontSee('Pending seller shop')
            ->assertDontSee('<table', false)
            ->assertSee(route('admin.seller-applications.index'), false)
            ->assertSee(route('admin.stores.show', $store), false);
    }

    public function test_administrator_can_view_store_details_with_verification_products_orders_and_activity(): void
    {
        $administrator = User::factory()->administrator()->create();
        $store = $this->store('Northwind Goods', 'active');
        $owner = $this->attachOwner($store, 'Ava Seller', 'ava@example.com');
        $application = $owner->sellerApplications()->create([
            'tenant_id' => $store->tenant_id,
            'store_name' => 'Northwind Goods',
            'store_description' => 'Useful things.',
            'seller_name' => 'Ava Seller',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'store_category' => 'General',
            'status' => 'approved',
            'submitted_at' => now()->subDays(5),
            'reviewed_at' => now()->subDays(4),
            'reviewed_by' => $administrator->id,
            'review_note' => 'Documents verified.',
        ]);
        $product = $this->product($store, 'Canvas Tote', 'TOTE-001', 'active');
        $sellerOrder = $this->sellerOrder($store, 'JM-000001');

        $response = $this->actingAs($administrator)->get(route('admin.stores.show', $store));

        $response->assertOk()
            ->assertSee('Northwind Goods')
            ->assertSee('ava@example.com')
            ->assertSee('+639171234567')
            ->assertSee('General')
            ->assertSee('Documents verified.')
            ->assertSee($product->name)
            ->assertSee($sellerOrder->order->number)
            ->assertSee('₱500.00');
        $this->assertNotNull($application->id);
    }

    public function test_suspend_requires_reason_updates_store_and_tenant_and_records_audit(): void
    {
        $administrator = User::factory()->administrator()->create();
        $store = $this->store('Northwind Goods', 'active');

        $this->actingAs($administrator)->patch(route('admin.stores.suspend', $store), ['reason' => 'short'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($administrator)->patch(route('admin.stores.suspend', $store), ['reason' => 'Repeated policy violations reported by buyers.'])
            ->assertRedirect(route('admin.stores.show', $store));

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'status' => 'suspended']);
        $this->assertDatabaseHas('tenants', ['id' => $store->tenant_id, 'status' => 'suspended']);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $administrator->id,
            'action' => 'store.suspended',
            'entity_type' => Store::class,
            'entity_id' => $store->id,
        ]);

        $this->actingAs($administrator)->patch(route('admin.stores.reactivate', $store))
            ->assertRedirect(route('admin.stores.show', $store));

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'status' => 'active']);
        $this->assertDatabaseHas('tenants', ['id' => $store->tenant_id, 'status' => 'active']);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $administrator->id,
            'action' => 'store.reactivated',
        ]);
    }

    public function test_reject_requires_reason_and_records_audit(): void
    {
        $administrator = User::factory()->administrator()->create();
        $seller = User::factory()->seller()->create();
        $application = $seller->sellerApplications()->create([
            'store_name' => 'Northwind Goods',
            'seller_name' => 'Ava Seller',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $this->actingAs($administrator)->post(route('admin.seller-applications.reject', $application), ['reason' => 'short'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($administrator)->post(route('admin.seller-applications.reject', $application), ['reason' => 'Incomplete business documents submitted.'])
            ->assertRedirect();

        $this->assertDatabaseHas('seller_applications', ['id' => $application->id, 'status' => 'rejected']);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $administrator->id,
            'action' => 'seller_application.rejected',
            'entity_type' => SellerApplication::class,
            'entity_id' => $application->id,
        ]);
    }

    public function test_non_administrators_cannot_access_store_governance_or_moderate(): void
    {
        $store = $this->store('Northwind Goods', 'active');
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();

        $this->actingAs($buyer)->get(route('admin.stores.index'))->assertForbidden();
        $this->actingAs($seller)->get(route('admin.stores.show', $store))->assertForbidden();
        $this->actingAs($seller)->patch(route('admin.stores.suspend', $store), ['reason' => 'Repeated policy violations reported by buyers.'])->assertForbidden();
        $this->actingAs($buyer)->patch(route('admin.stores.reactivate', $store))->assertForbidden();
    }

    private function store(string $name, string $status): Store
    {
        $slug = str($name)->slug().'-'.str()->lower(str()->random(6));
        $tenant = Tenant::create(['name' => $name, 'slug' => $slug.'-tenant', 'status' => $status === 'suspended' ? 'suspended' : 'active']);

        return Store::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'slug' => $slug,
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => $status,
        ]);
    }

    private function attachOwner(Store $store, string $name, string $email): User
    {
        $owner = User::factory()->seller()->create(['name' => $name, 'email' => $email]);
        $store->tenant->members()->create(['user_id' => $owner->id, 'role' => 'owner']);

        return $owner;
    }

    private function product(Store $store, string $name, string $sku, string $status, ?string $categoryId = null): Product
    {
        return Product::create([
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->lower(str()->random(6)),
            'sku' => $sku,
            'price' => '299.00',
            'stock_quantity' => 12,
            'status' => $status,
        ]);
    }

    private function sellerOrder(Store $store, string $number): SellerOrder
    {
        $buyer = User::factory()->create();
        $order = Order::create([
            'user_id' => $buyer->id,
            'number' => $number,
            'status' => 'pending',
            'recipient_name' => $buyer->name,
            'phone' => '+639171234567',
            'address_line' => '100 Market Street',
            'city' => 'Manila',
            'subtotal' => '500.00',
            'shipping_amount' => '0.00',
            'total' => '500.00',
        ]);
        $order->payment()->create(['method' => 'cod', 'status' => 'pending', 'amount' => '500.00']);

        return $order->sellerOrders()->create([
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
            'status' => 'pending',
            'subtotal' => '500.00',
            'shipping_amount' => '0.00',
            'total' => '500.00',
        ]);
    }
}
