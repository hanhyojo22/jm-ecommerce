<?php

namespace Tests\Feature;

use App\Jobs\SendAdminAnnouncement;
use App\Livewire\NotificationBell;
use App\Models\Announcement;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use App\Notifications\MarketplaceNotice;
use App\Services\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class AdminAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_images_are_optional_https_urls_saved_with_the_announcement(): void
    {
        Queue::fake();
        $admin = User::factory()->administrator()->create();
        $payload = ['audience' => 'customers', 'title' => 'Fresh finds', 'body' => 'Explore the collection.', 'image_url' => 'https://example.com/campaign.jpg'];
        $this->actingAs($admin)->post(route('admin.announcements.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('announcements', ['image_url' => 'https://example.com/campaign.jpg']);
        $this->post(route('admin.announcements.store'), array_replace($payload, ['image_url' => 'http://example.com/campaign.jpg']))->assertSessionHasErrors('image_url');
        $this->assertDatabaseCount('announcements', 1);
        $this->post(route('admin.announcements.store'), array_replace($payload, ['image_url' => rtrim(config('app.url'), '/').'/storage/products/tote.jpg']))->assertSessionHasNoErrors();
    }

    public function test_an_uploaded_campaign_image_is_stored_and_saved_as_the_announcement_image(): void
    {
        Queue::fake();
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'audience' => 'customers',
            'title' => 'Fresh finds',
            'body' => 'Explore the collection.',
            'campaign_image' => UploadedFile::fake()->image('campaign.jpg', 1200, 628),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $announcement = Announcement::query()->sole();
        $storedFiles = Storage::disk('public')->allFiles('announcements');
        $this->assertCount(1, $storedFiles);
        $this->assertStringContainsString(basename($storedFiles[0]), $announcement->image_url);
    }

    public function test_an_administrator_queues_an_announcement_and_it_is_audited(): void
    {
        Queue::fake();
        $administrator = User::factory()->administrator()->create();
        User::factory()->count(2)->create();

        $this->actingAs($administrator)->get(route('admin.announcements.create'))->assertOk()->assertSee('Send notification');

        $this->actingAs($administrator)
            ->post(route('admin.announcements.store'), ['audience' => 'customers', 'title' => 'Big sale', 'body' => 'Everything 20% off today.'])
            ->assertRedirect(route('admin.announcements.create', ['tab' => 'history']))
            ->assertSessionHas('status', 'Notification queued for 2 recipient(s).');

        $announcement = Announcement::query()->sole();
        $this->assertSame(['customers', route('home'), 2], [$announcement->audience, $announcement->url, $announcement->recipient_count]);
        Queue::assertPushed(SendAdminAnnouncement::class, fn (SendAdminAnnouncement $job): bool => $job->announcementId === $announcement->id && $job->batchUserIds === null);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $administrator->id, 'action' => 'announcement.sent', 'entity_id' => $announcement->id]);

        $this->actingAs($administrator)->get(route('admin.announcements.create', ['tab' => 'history']))->assertSee('Big sale')->assertSee('0/2 sent');
    }

    public function test_a_specific_customer_must_exist(): void
    {
        Queue::fake();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('admin.announcements.store'), ['audience' => 'customer', 'email' => 'nobody@example.com', 'title' => 'Hi', 'body' => 'Hello'])
            ->assertSessionHasErrors(['email']);

        Queue::assertNothingPushed();
    }

    public function test_a_customer_is_found_by_email_regardless_of_case(): void
    {
        Queue::fake();
        $administrator = User::factory()->administrator()->create();
        $customer = User::factory()->create(['email' => 'ana@example.com']);

        $this->actingAs($administrator)
            ->post(route('admin.announcements.store'), ['audience' => 'customer', 'email' => 'Ana@Example.com', 'title' => 'Hi', 'body' => 'Hello'])
            ->assertSessionHasNoErrors();

        $this->assertSame($customer->id, Announcement::query()->sole()->user_id);
    }

    public function test_the_link_must_be_a_jmmart_page(): void
    {
        Queue::fake();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->post(route('admin.announcements.store'), ['audience' => 'customers', 'title' => 'Hi', 'body' => 'Hello', 'url' => 'https://evil.example.com/login'])
            ->assertSessionHasErrors(['url']);

        $this->actingAs($administrator)
            ->post(route('admin.announcements.store'), ['audience' => 'customers', 'title' => 'Hi', 'body' => 'Hello', 'url' => route('cart.index')])
            ->assertSessionHasNoErrors();

        Queue::assertPushed(SendAdminAnnouncement::class, 1);
    }

    public function test_link_targets_list_only_active_stores(): void
    {
        $administrator = User::factory()->administrator()->create();
        $this->storeOwner('active');
        $this->storeOwner('suspended');

        $activeStore = Store::query()->where('status', 'active')->sole();
        $suspendedStore = Store::query()->where('status', 'suspended')->sole();

        $this->actingAs($administrator)
            ->getJson(route('admin.announcements.link-targets', ['type' => 'stores']))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.name', $activeStore->name)
            ->assertDontSee($suspendedStore->name);
    }

    public function test_a_store_deep_link_is_a_valid_campaign_link(): void
    {
        Queue::fake();
        $administrator = User::factory()->administrator()->create();
        $this->storeOwner('active');
        $store = Store::query()->sole();

        $this->actingAs($administrator)
            ->post(route('admin.announcements.store'), [
                'audience' => 'customers',
                'title' => 'Fresh finds',
                'body' => 'Shop the new arrivals.',
                'url' => route('catalog.store', $store->slug),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(route('catalog.store', $store->slug), Announcement::query()->sole()->url);
    }

    public function test_a_store_or_product_link_must_still_be_public(): void
    {
        Queue::fake();
        $administrator = User::factory()->administrator()->create();
        $this->storeOwner('suspended');
        $store = Store::query()->sole();
        Product::create(['tenant_id' => $store->tenant_id, 'store_id' => $store->id, 'name' => 'Canvas Tote', 'slug' => 'canvas-tote', 'sku' => 'TOTE', 'price' => 199, 'stock_quantity' => 3, 'status' => 'active']);
        $send = fn (string $url) => $this->actingAs($administrator)->post(route('admin.announcements.store'), ['audience' => 'customers', 'title' => 'Hi', 'body' => 'Hello', 'url' => $url]);

        $send(route('catalog.store', $store->slug))->assertSessionHasErrors(['url' => 'That store is no longer available. Choose another.']);
        $send(route('catalog.show', ['store' => $store->slug, 'product' => 'canvas-tote']))->assertSessionHasErrors(['url' => 'That product is no longer available. Choose another.']);

        Queue::assertNothingPushed();
    }

    public function test_non_administrators_cannot_send_or_cancel_announcements(): void
    {
        Queue::fake();
        $customer = User::factory()->create();
        $announcement = $this->announcement('customers', 1);

        $this->actingAs($customer)
            ->post(route('admin.announcements.store'), ['audience' => 'customers', 'title' => 'Hi', 'body' => 'Hello'])
            ->assertForbidden();
        $this->actingAs($customer)->patch(route('admin.announcements.cancel', $announcement))->assertForbidden();

        Queue::assertNothingPushed();
        $this->assertNull($announcement->fresh()->cancelled_at);
    }

    public function test_each_audience_reaches_only_its_active_recipients(): void
    {
        $customer = User::factory()->create();
        User::factory()->create(['is_active' => false]);
        $activeStoreOwner = $this->storeOwner('active');
        $this->storeOwner('suspended');

        $this->assertEquals([$customer->id], SendAdminAnnouncement::recipients('customers')->pluck('id')->all());
        $this->assertEquals([$activeStoreOwner->id], SendAdminAnnouncement::recipients('stores')->pluck('id')->all());
        $this->assertEquals([$customer->id], SendAdminAnnouncement::recipients('customer', $customer->id)->pluck('id')->all());
    }

    public function test_the_job_fans_out_into_small_batches(): void
    {
        Queue::fake();
        User::factory()->count(30)->create();
        $announcement = $this->announcement('customers', 30);

        (new SendAdminAnnouncement($announcement->id))->handle(Mockery::mock(PushNotifier::class));

        Queue::assertPushed(SendAdminAnnouncement::class, 2);
        Queue::assertPushed(SendAdminAnnouncement::class, fn (SendAdminAnnouncement $job): bool => count($job->batchUserIds) === 25);
    }

    public function test_a_batch_pushes_notifies_and_counts_its_recipients(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $announcement = $this->announcement('customers', 1);
        $announcement->update(['image_url' => 'https://cdn.example.com/campaign.jpg']);

        $notifier = Mockery::mock(PushNotifier::class);
        $notifier->shouldReceive('sendToUser')
            ->once()
            ->with(Mockery::on(fn (User $user): bool => $user->is($customer)), 'Big sale', 'Everything 20% off.', ['type' => 'announcement', 'url' => $announcement->url, 'image_url' => 'https://cdn.example.com/campaign.jpg', 'app_path' => parse_url($announcement->url, PHP_URL_PATH) ?: '/']);

        (new SendAdminAnnouncement($announcement->id, [$customer->id]))->handle($notifier);

        Notification::assertSentTo(
            $customer,
            MarketplaceNotice::class,
            fn (MarketplaceNotice $notification): bool => ($notification->toArray($customer)['image_url'] ?? null) === 'https://cdn.example.com/campaign.jpg',
        );
        $this->assertSame(1, $announcement->fresh()->sent_count);
        $this->assertTrue($announcement->fresh()->isFinished());
    }

    public function test_link_targets_search_one_type_and_can_narrow_products_to_a_store(): void
    {
        $tenant = Tenant::create(['name' => 'Northwind', 'slug' => 'northwind', 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => 'Northwind Store', 'slug' => 'northwind-store', 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        $otherTenant = Tenant::create(['name' => 'Southwind', 'slug' => 'southwind', 'status' => 'active']);
        $otherStore = Store::create(['tenant_id' => $otherTenant->id, 'name' => 'Southwind Store', 'slug' => 'southwind-store', 'phone' => '+639171234567', 'address_line' => '11 Market Street', 'city' => 'Cebu', 'status' => 'active']);
        $product = fn (Store $owner, string $name, string $slug, string $status): Product => Product::create(['tenant_id' => $owner->tenant_id, 'store_id' => $owner->id, 'name' => $name, 'slug' => $slug, 'sku' => strtoupper($slug), 'price' => 199, 'stock_quantity' => 3, 'status' => $status]);
        Storage::fake('product_images');
        $product($store, 'Canvas Tote', 'canvas-tote', 'active')->images()->create(['path' => 'products/canvas-tote.jpg', 'position' => 0]);
        $product($store, 'Canvas Draft', 'canvas-draft', 'draft');
        $product($otherStore, 'Canvas Pouch', 'canvas-pouch', 'active');

        $this->actingAs(User::factory()->administrator()->create())
            ->getJson(route('admin.announcements.link-targets', ['type' => 'products', 'q' => 'canvas']))
            ->assertOk()
            ->assertJsonCount(2, 'results')
            ->assertJsonPath('results.0.name', 'Canvas Pouch')
            ->assertJsonPath('results.1.name', 'Canvas Tote')
            ->assertJsonPath('results.1.store', 'Northwind Store')
            ->assertJsonPath('results.1.price', '₱199.00')
            ->assertJsonPath('results.1.url', route('catalog.show', ['store' => 'northwind-store', 'product' => 'canvas-tote']))
            ->assertJsonPath('results.1.image', Storage::disk('product_images')->url('products/canvas-tote.jpg'));

        $this->getJson(route('admin.announcements.link-targets', ['type' => 'products', 'q' => 'canvas', 'store' => 'northwind-store']))
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.name', 'Canvas Tote');

        $this->getJson(route('admin.announcements.link-targets', ['type' => 'stores', 'q' => 'north']))
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.slug', 'northwind-store')
            ->assertJsonPath('results.0.city', 'Manila')
            ->assertJsonPath('results.0.url', route('catalog.store', 'northwind-store'));

        $this->getJson(route('admin.announcements.link-targets', ['q' => 'canvas']))->assertUnprocessable();

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.announcements.link-targets', ['type' => 'products']))
            ->assertForbidden();
    }

    public function test_a_failed_submit_keeps_the_picked_product(): void
    {
        $tenant = Tenant::create(['name' => 'Northwind', 'slug' => 'northwind', 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => 'Northwind Store', 'slug' => 'northwind-store', 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        Product::create(['tenant_id' => $tenant->id, 'store_id' => $store->id, 'name' => 'Canvas Tote', 'slug' => 'canvas-tote', 'sku' => 'TOTE', 'price' => 199, 'stock_quantity' => 3, 'status' => 'active']);

        $this->actingAs(User::factory()->administrator()->create())
            ->from(route('admin.announcements.create'))
            ->followingRedirects()
            ->post(route('admin.announcements.store'), ['audience' => 'customers', 'title' => '', 'body' => 'Hello', 'url' => route('catalog.show', ['store' => 'northwind-store', 'product' => 'canvas-tote'])])
            ->assertOk()
            ->assertSee('Canvas Tote');
    }

    public function test_storefront_links_become_native_store_deep_links(): void
    {
        $this->assertSame('northwind-store', SendAdminAnnouncement::storeSlug(route('catalog.store', 'northwind-store')));
        $this->assertNull(SendAdminAnnouncement::storeSlug(route('catalog.show', ['store' => 'northwind-store', 'product' => 'tote'])));
        $this->assertNull(SendAdminAnnouncement::storeSlug(route('catalog.index')));
    }

    public function test_a_store_campaign_sends_the_store_slug_to_mobile_pushes(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $announcement = $this->announcement('customers', 1);
        $announcement->update(['url' => route('catalog.store', 'northwind-store')]);

        $notifier = Mockery::mock(PushNotifier::class);
        $notifier->shouldReceive('sendToUser')
            ->once()
            ->with(Mockery::on(fn (User $user): bool => $user->is($customer)), 'Big sale', 'Everything 20% off.', [
                'type' => 'announcement',
                'url' => route('catalog.store', 'northwind-store'),
                'store_slug' => 'northwind-store',
                'app_path' => '/stores/northwind-store',
            ]);

        (new SendAdminAnnouncement($announcement->id, [$customer->id]))->handle($notifier);
    }

    public function test_a_product_campaign_sends_the_product_path_to_mobile_pushes(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $announcement = $this->announcement('customers', 1);
        $productUrl = route('catalog.show', ['store' => 'northwind-store', 'product' => 'canvas-tote']);
        $announcement->update(['url' => $productUrl]);

        $notifier = Mockery::mock(PushNotifier::class);
        $notifier->shouldReceive('sendToUser')
            ->once()
            ->with(Mockery::on(fn (User $user): bool => $user->is($customer)), 'Big sale', 'Everything 20% off.', [
                'type' => 'announcement',
                'url' => $productUrl,
                'app_path' => '/stores/northwind-store/products/canvas-tote',
            ]);

        (new SendAdminAnnouncement($announcement->id, [$customer->id]))->handle($notifier);
    }

    public function test_cancelling_stops_batches_not_yet_sent(): void
    {
        Notification::fake();
        $administrator = User::factory()->administrator()->create();
        $customer = User::factory()->create();
        $announcement = $this->announcement('customers', 1);

        $this->actingAs($administrator)
            ->patch(route('admin.announcements.cancel', $announcement))
            ->assertSessionHas('status', 'Notification cancelled. Batches not yet sent were stopped.');

        $notifier = Mockery::mock(PushNotifier::class);
        $notifier->shouldNotReceive('sendToUser');
        (new SendAdminAnnouncement($announcement->id, [$customer->id]))->handle($notifier);

        Notification::assertNothingSentTo($customer);
        $this->assertDatabaseHas('audit_logs', ['action' => 'announcement.cancelled', 'entity_id' => $announcement->id]);
    }

    public function test_clicking_an_announcement_in_the_bell_opens_its_link(): void
    {
        $customer = User::factory()->create();
        $customer->notify(new MarketplaceNotice('announcement', 'Big sale', 'Everything 20% off.', route('cart.index')));

        Livewire::actingAs($customer)
            ->test(NotificationBell::class)
            ->call('goTo', $customer->notifications()->sole()->id)
            ->assertRedirect(route('cart.index'));
    }

    private function announcement(string $audience, int $recipientCount): Announcement
    {
        return Announcement::query()->create([
            'audience' => $audience,
            'title' => 'Big sale',
            'body' => 'Everything 20% off.',
            'url' => route('home'),
            'recipient_count' => $recipientCount,
        ]);
    }

    private function storeOwner(string $status): User
    {
        $owner = User::factory()->seller()->create();
        $suffix = str()->lower(str()->random(8));
        $tenant = Tenant::create(['name' => "Tenant {$suffix}", 'slug' => "tenant-{$suffix}", 'status' => $status]);
        Store::create([
            'tenant_id' => $tenant->id,
            'name' => "Store {$suffix}",
            'slug' => "store-{$suffix}",
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => $status,
        ]);
        TenantMember::create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner']);

        return $owner;
    }
}
