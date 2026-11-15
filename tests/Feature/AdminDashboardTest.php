<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_navigation_persists_and_tracks_the_active_section(): void
    {
        $administrator = User::factory()->administrator()->create();
        $this->actingAs($administrator);

        foreach ([
            'admin.moderation' => 'Moderation',
            'admin.products.index' => 'Products',
            'admin.audit-logs.index' => 'Audit log',
        ] as $route => $label) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('aria-label="Admin workspace navigation"', false)
                ->assertSee(route($route), false)
                ->assertSee($label)
                ->assertSee('aria-current=page', false);
        }
    }

    public function test_administrator_sees_the_marketplace_overview_with_real_orders_and_moderation_links(): void
    {
        $administrator = User::factory()->administrator()->create();
        $context = $this->productContext();
        $storeWithoutSales = $this->store('Store Without Sales', 'active');
        Product::create([
            'tenant_id' => $storeWithoutSales->tenant_id,
            'store_id' => $storeWithoutSales->id,
            'name' => 'Unsold Product',
            'slug' => 'unsold-product',
            'sku' => 'UNSOLD',
            'price' => 100,
            'stock_quantity' => 1,
            'status' => 'active',
        ]);

        $this->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Marketplace overview')
            ->assertSee('Orders in the last 7 days')
            ->assertSee($context['item']->sellerOrder->order->number)
            ->assertSee($context['store']->name)
            ->assertSee($context['product']->name)
            ->assertDontSee($storeWithoutSales->name)
            ->assertDontSee('Unsold Product')
            ->assertSee(route('admin.moderation', ['type' => 'product_report']), false);
    }

    public function test_marketplace_totals_use_one_order_aggregate(): void
    {
        $administrator = User::factory()->administrator()->create();
        $context = $this->productContext();
        Order::create([
            'user_id' => $context['buyer']->id,
            'number' => 'CANCELLED-STATS',
            'status' => 'cancelled',
            'recipient_name' => 'Buyer',
            'phone' => '+639171234567',
            'address_line' => '1 Market Street',
            'city' => 'Manila',
            'subtotal' => 900,
            'shipping_amount' => 0,
            'total' => 900,
        ]);
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->actingAs($administrator)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('marketplaceStats', fn (array $stats): bool => $stats['gmv'] === '100.00' && $stats['orders'] === 1);

        $this->assertCount(1, array_filter($queries, fn (string $sql): bool => str_contains($sql, 'from "orders"') && str_contains($sql, '"status" !=') && ! str_contains($sql, 'created_at')));
    }

    public function test_administrator_sees_live_moderation_counts_and_each_queue_type(): void
    {
        $administrator = User::factory()->administrator()->create();
        $this->store('Harbor Market', 'suspended');

        $context = $this->productContext();
        $review = ProductReview::create([
            'product_id' => $context['product']->id,
            'order_item_id' => $context['item']->id,
            'user_id' => $context['buyer']->id,
            'rating' => 2,
            'title' => 'Reported review',
            'body' => 'A review that needs moderation.',
            'status' => 'published',
        ]);
        $reviewReporter = User::factory()->create(['name' => 'Review reporter']);
        $review->reports()->create(['user_id' => $reviewReporter->id, 'reason' => 'spam']);

        $response = $this->actingAs($administrator)->get(route('admin.moderation'));

        $response->assertOk()
            ->assertSee('Moderation')
            ->assertSee('1 item need attention')
            ->assertSee($context['product']->name)
            ->assertSee('Subject / context')
            ->assertSee('Reported reviews')
            ->assertSee('Review author: '.$context['buyer']->name)
            ->assertSee('Governance shortcuts')
            ->assertSee('Suspended stores')
            ->assertSee(route('admin.reviews.index', ['review' => $review->id]).'#review-'.$review->id, false);

        $this->actingAs($administrator)
            ->get(route('admin.reviews.index', ['review' => $review->id]))
            ->assertOk()
            ->assertSee($context['product']->name)
            ->assertSee('A review that needs moderation.');
    }

    public function test_category_queue_links_to_all_items_when_dashboard_limit_is_exceeded(): void
    {
        $administrator = User::factory()->administrator()->create();

        foreach (range(1, 13) as $index) {
            $this->createReportedReview("Reported product {$index}");
        }

        $this->actingAs($administrator)
            ->get(route('admin.moderation', ['type' => 'product_report']))
            ->assertSee('Showing 12 oldest of 13 items.')
            ->assertSee(route('admin.reviews.index', ['reported' => 1]), false);
    }

    public function test_dashboard_sorts_by_received_time_and_rejects_unknown_sort_orders(): void
    {
        $administrator = User::factory()->administrator()->create();
        $this->travelTo(now()->subDays(2), fn () => $this->createReportedReview('Older reported lamp'));
        $this->createReportedReview('Newer reported lamp');

        $this->actingAs($administrator)
            ->get(route('admin.moderation', ['type' => 'product_report']))
            ->assertSeeTextInOrder(['Older reported lamp', 'Newer reported lamp']);

        $this->actingAs($administrator)
            ->get(route('admin.moderation', ['type' => 'product_report', 'sort' => 'newest']))
            ->assertSeeTextInOrder(['Newer reported lamp', 'Older reported lamp']);

        $this->followingRedirects()
            ->from(route('admin.moderation'))
            ->actingAs($administrator)
            ->get(route('admin.moderation', ['sort' => 'priority']))
            ->assertSee('That queue view is no longer available.')
            ->assertSee('Reset to all items');
    }

    public function test_reported_review_filter_matches_the_dashboard_queue(): void
    {
        $administrator = User::factory()->administrator()->create();
        $context = $this->productContext();
        $reportedReview = ProductReview::create([
            'product_id' => $context['product']->id,
            'order_item_id' => $context['item']->id,
            'user_id' => $context['buyer']->id,
            'rating' => 2,
            'title' => 'Reported item',
            'body' => 'This review has a report.',
            'status' => 'published',
        ]);
        $reportedReview->reports()->create(['user_id' => User::factory()->create()->id, 'reason' => 'spam']);
        $unreportedItem = $context['item']->replicate();
        $unreportedItem->save();
        ProductReview::create([
            'product_id' => $context['product']->id,
            'order_item_id' => $unreportedItem->id,
            'user_id' => $context['buyer']->id,
            'rating' => 5,
            'title' => 'Unreported item',
            'body' => 'This review has no report.',
            'status' => 'published',
        ]);

        $this->actingAs($administrator)
            ->get(route('admin.reviews.index', ['reported' => 1]))
            ->assertOk()
            ->assertSee('Reported review moderation')
            ->assertSee('This review has a report.')
            ->assertDontSee('This review has no report.');
    }

    public function test_dashboard_shows_an_empty_state_when_no_items_need_moderation(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get(route('admin.moderation'))
            ->assertOk()
            ->assertSee('All caught up')
            ->assertSee('No items in this queue');
    }

    public function test_non_administrators_cannot_access_dashboard(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();

        $this->actingAs($buyer)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($seller)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($buyer)->get(route('admin.moderation'))->assertForbidden();
        $this->actingAs($seller)->get(route('admin.moderation'))->assertForbidden();
    }

    private function store(string $name, string $status): Store
    {
        $slug = str($name)->slug().'-'.str()->lower(str()->random(6));
        $tenant = Tenant::create(['name' => $name, 'slug' => $slug.'-tenant', 'status' => $status === 'suspended' ? 'suspended' : 'active']);

        return Store::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'slug' => $slug,
            'description' => 'A fine store selling useful goods.',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => $status,
        ]);
    }

    private function attachOwner(Store $store, string $name, string $email): User
    {
        $owner = User::factory()->seller()->create(['name' => $name, 'email' => $email]);
        TenantMember::create(['tenant_id' => $store->tenant_id, 'user_id' => $owner->id, 'role' => 'owner']);

        return $owner;
    }

    private function createReportedReview(string $productName): void
    {
        $context = $this->productContext($productName);
        $review = ProductReview::create([
            'product_id' => $context['product']->id,
            'order_item_id' => $context['item']->id,
            'user_id' => $context['buyer']->id,
            'rating' => 1,
            'body' => 'Not as described.',
            'status' => 'published',
        ]);
        $review->reports()->create(['user_id' => User::factory()->create()->id, 'reason' => 'spam']);
    }

    /** @return array{store:Store,seller:User,buyer:User,product:Product,item:OrderItem} */
    private function productContext(string $productName = 'Reported table lamp'): array
    {
        $suffix = str()->lower(str()->random(8));
        $store = $this->store("Marketplace {$suffix}", 'active');
        $seller = $this->attachOwner($store, 'Marketplace Seller', "seller-{$suffix}@example.com");
        $buyer = User::factory()->create(['name' => 'Marketplace Buyer']);
        $product = Product::create([
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
            'name' => $productName,
            'slug' => "reported-lamp-{$suffix}",
            'sku' => "SKU-{$suffix}",
            'price' => 100,
            'stock_quantity' => 1,
            'status' => 'active',
        ]);
        $order = Order::create([
            'user_id' => $buyer->id,
            'number' => "ORD-{$suffix}",
            'status' => 'delivered',
            'recipient_name' => 'Buyer',
            'phone' => '+639171234567',
            'address_line' => '1 Market Street',
            'city' => 'Manila',
            'subtotal' => 100,
            'shipping_amount' => 0,
            'total' => 100,
        ]);
        $sellerOrder = $order->sellerOrders()->create([
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
            'status' => 'delivered',
            'subtotal' => 100,
            'shipping_amount' => 0,
            'total' => 100,
        ]);
        $item = $sellerOrder->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 100,
            'quantity' => 1,
            'line_total' => 100,
        ]);

        return compact('store', 'seller', 'buyer', 'product', 'item');
    }
}
