<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\MessageReport;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_navigation_persists_and_tracks_the_active_section(): void
    {
        $administrator = User::factory()->administrator()->create();
        $this->actingAs($administrator);

        foreach ([
            'admin.seller-applications.index' => 'Seller applications',
            'admin.products.index' => 'Products',
            'admin.audit-logs.index' => 'Audit log',
        ] as $route => $label) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('aria-label="Admin workspace navigation"', false)
                ->assertSee('aria-label="Breadcrumb"', false)
                ->assertSeeInOrder(['id="main-content"', $label])
                ->assertSee(route($route), false)
                ->assertSee($label)
                ->assertSee('aria-current=page', false);
        }
    }

    public function test_administrator_sees_live_moderation_counts_and_each_queue_type(): void
    {
        $administrator = User::factory()->administrator()->create();
        $seller = User::factory()->seller()->create();
        $seller->sellerApplications()->create([
            'store_name' => 'Pending Shop',
            'seller_name' => 'Pia Pending',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $suspendedStore = $this->store('Harbor Market', 'suspended');
        $appealOwner = $this->attachOwner($suspendedStore, 'Ben Seller', 'ben@example.com');
        $appeal = $suspendedStore->appeals()->create([
            'seller_id' => $appealOwner->id,
            'statement' => 'The suspension was caused by an incorrect listing report.',
            'status' => 'pending',
        ]);

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

        $conversation = Conversation::create([
            'buyer_id' => $context['buyer']->id,
            'store_id' => $context['store']->id,
            'product_id' => $context['product']->id,
        ]);
        $message = $conversation->messages()->create([
            'sender_id' => $context['seller']->id,
            'type' => 'text',
            'body' => 'A reported private message.',
        ]);
        $chatReport = MessageReport::create([
            'conversation_message_id' => $message->id,
            'reporter_id' => $context['buyer']->id,
            'reason' => 'harassment',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($administrator)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Moderation')
            ->assertSee('4 items need attention')
            ->assertSee('Pending Shop')
            ->assertSee('Harbor Market')
            ->assertSee($context['product']->name)
            ->assertSee('Subject / context')
            ->assertSee('Reported reviews')
            ->assertSee('Review author: '.$context['buyer']->name)
            ->assertSee('Reported by '.$context['buyer']->name)
            ->assertSee('Harassment')
            ->assertSee('Governance shortcuts')
            ->assertSee('Suspended stores')
            ->assertSee('Pending applications')
            ->assertSee(route('admin.seller-applications.show', $seller->sellerApplications()->first()), false)
            ->assertSee(route('admin.stores.show', $suspendedStore).'#suspension-appeals', false)
            ->assertSee(route('admin.reviews.index', ['review' => $review->id]).'#review-'.$review->id, false)
            ->assertSee(route('admin.chat-reports.index', ['report' => $chatReport->id]).'#chat-report-'.$chatReport->id, false);

        $this->actingAs($administrator)
            ->get(route('admin.reviews.index', ['review' => $review->id]))
            ->assertOk()
            ->assertSee($context['product']->name)
            ->assertSee('A review that needs moderation.');

        $this->actingAs($administrator)
            ->get(route('admin.chat-reports.index', ['report' => $chatReport->id]))
            ->assertOk()
            ->assertSee('harassment')
            ->assertSee('A reported private message.');

        $this->actingAs($administrator)
            ->patch(route('admin.chat-reports.update', $chatReport), ['status' => 'reviewed'])
            ->assertRedirect();

        $this->assertDatabaseHas('message_reports', ['id' => $chatReport->id, 'status' => 'reviewed']);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $administrator->id,
            'action' => 'message_report.reviewed',
            'entity_id' => $chatReport->id,
        ]);
    }

    public function test_dashboard_category_filter_only_shows_matching_moderation_items(): void
    {
        $administrator = User::factory()->administrator()->create();
        $seller = User::factory()->seller()->create();
        $seller->sellerApplications()->create([
            'store_name' => 'Pending Shop',
            'seller_name' => 'Pia Pending',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
        $suspendedStore = $this->store('Harbor Market', 'suspended');
        $owner = $this->attachOwner($suspendedStore, 'Ben Seller', 'ben@example.com');
        $suspendedStore->appeals()->create([
            'seller_id' => $owner->id,
            'statement' => 'Please review this suspension decision.',
            'status' => 'pending',
        ]);

        $this->actingAs($administrator)
            ->get(route('dashboard', ['type' => 'appeal']))
            ->assertOk()
            ->assertSee('Harbor Market')
            ->assertSee('Store appeal')
            ->assertDontSee('Pending Shop');
    }

    public function test_category_queue_links_to_all_items_when_dashboard_limit_is_exceeded(): void
    {
        $administrator = User::factory()->administrator()->create();

        foreach (range(1, 13) as $index) {
            $seller = User::factory()->seller()->create();
            $seller->sellerApplications()->create([
                'store_name' => "Pending Shop {$index}",
                'seller_name' => "Seller {$index}",
                'phone' => '+639171234567',
                'address_line' => '10 Market Street',
                'city' => 'Manila',
                'status' => 'pending',
                'submitted_at' => now(),
            ]);
        }

        $this->actingAs($administrator)
            ->get(route('dashboard', ['type' => 'application']))
            ->assertSee('Showing 12 oldest of 13 items.')
            ->assertSee(route('admin.seller-applications.index', ['status' => 'pending']), false);
    }

    public function test_dashboard_sorts_by_received_time_and_rejects_unknown_sort_orders(): void
    {
        $administrator = User::factory()->administrator()->create();
        $this->createPendingApplication('Older Pending Shop', now()->subDays(2));
        $this->createPendingApplication('Newer Pending Shop', now());

        $this->actingAs($administrator)
            ->get(route('dashboard', ['type' => 'application']))
            ->assertSeeTextInOrder(['Older Pending Shop', 'Newer Pending Shop']);

        $this->actingAs($administrator)
            ->get(route('dashboard', ['type' => 'application', 'sort' => 'newest']))
            ->assertSeeTextInOrder(['Newer Pending Shop', 'Older Pending Shop']);

        $this->followingRedirects()
            ->from(route('dashboard'))
            ->actingAs($administrator)
            ->get(route('dashboard', ['sort' => 'priority']))
            ->assertSee('That queue view is no longer available.')
            ->assertSee('Reset to all items');
    }

    public function test_governance_list_filters_match_pending_dashboard_queues(): void
    {
        $administrator = User::factory()->administrator()->create();
        $pendingStore = $this->store('Pending Appeal Store', 'suspended');
        $pendingOwner = $this->attachOwner($pendingStore, 'Appeal Owner', 'appeal-owner@example.com');
        $pendingStore->appeals()->create([
            'seller_id' => $pendingOwner->id,
            'statement' => 'Please review this suspension.',
            'status' => 'pending',
        ]);
        $activeAppealStore = $this->store('Active Appeal Store', 'active');
        $activeAppealOwner = $this->attachOwner($activeAppealStore, 'Active Appeal Owner', 'active-appeal-owner@example.com');
        $activeAppealStore->appeals()->create([
            'seller_id' => $activeAppealOwner->id,
            'statement' => 'This pending appeal should remain in the matching list.',
            'status' => 'pending',
        ]);
        $otherStore = $this->store('No Appeal Store', 'suspended');

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

        $conversation = Conversation::create([
            'buyer_id' => $context['buyer']->id,
            'store_id' => $context['store']->id,
            'product_id' => $context['product']->id,
        ]);
        $pendingMessage = $conversation->messages()->create([
            'sender_id' => $context['seller']->id,
            'type' => 'text',
            'body' => 'Pending report message.',
        ]);
        $reviewedMessage = $conversation->messages()->create([
            'sender_id' => $context['seller']->id,
            'type' => 'text',
            'body' => 'Already reviewed message.',
        ]);
        MessageReport::create([
            'conversation_message_id' => $pendingMessage->id,
            'reporter_id' => $context['buyer']->id,
            'reason' => 'spam',
            'status' => 'pending',
        ]);
        MessageReport::create([
            'conversation_message_id' => $reviewedMessage->id,
            'reporter_id' => $context['buyer']->id,
            'reason' => 'spam',
            'status' => 'reviewed',
        ]);

        $this->actingAs($administrator)
            ->get(route('admin.stores.index', ['queue' => 'pending_appeals']))
            ->assertOk()
            ->assertSee('Stores with pending appeals')
            ->assertSee('Pending Appeal Store')
            ->assertSee('Active Appeal Store')
            ->assertDontSee('No Appeal Store');

        $this->actingAs($administrator)
            ->get(route('admin.reviews.index', ['reported' => 1]))
            ->assertOk()
            ->assertSee('Reported review moderation')
            ->assertSee('This review has a report.')
            ->assertDontSee('This review has no report.');

        $this->actingAs($administrator)
            ->get(route('admin.chat-reports.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Pending chat reports')
            ->assertSee('Pending report message.')
            ->assertDontSee('Already reviewed message.');
    }

    public function test_dashboard_shows_an_empty_state_when_no_items_need_moderation(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get(route('dashboard'))
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

    private function createPendingApplication(string $storeName, Carbon $submittedAt): void
    {
        $seller = User::factory()->seller()->create();
        $seller->sellerApplications()->create([
            'store_name' => $storeName,
            'seller_name' => 'Pending Seller',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => 'pending',
            'submitted_at' => $submittedAt,
        ]);
    }

    /** @return array{store:Store,seller:User,buyer:User,product:Product,item:OrderItem} */
    private function productContext(): array
    {
        $suffix = str()->lower(str()->random(8));
        $store = $this->store("Marketplace {$suffix}", 'active');
        $seller = $this->attachOwner($store, 'Marketplace Seller', "seller-{$suffix}@example.com");
        $buyer = User::factory()->create(['name' => 'Marketplace Buyer']);
        $product = Product::create([
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->id,
            'name' => 'Reported table lamp',
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
