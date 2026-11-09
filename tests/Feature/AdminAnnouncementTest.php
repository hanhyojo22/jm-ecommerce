<?php

namespace Tests\Feature;

use App\Jobs\SendAdminAnnouncement;
use App\Livewire\NotificationBell;
use App\Models\Announcement;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use App\Notifications\MarketplaceNotice;
use App\Services\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class AdminAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_queues_an_announcement_and_it_is_audited(): void
    {
        Queue::fake();
        $administrator = User::factory()->administrator()->create();
        User::factory()->count(2)->create();

        $this->actingAs($administrator)->get(route('admin.announcements.create'))->assertOk()->assertSee('Send notification');

        $this->actingAs($administrator)
            ->post(route('admin.announcements.store'), ['audience' => 'customers', 'title' => 'Big sale', 'body' => 'Everything 20% off today.'])
            ->assertRedirect(route('admin.announcements.create'))
            ->assertSessionHas('status', 'Notification queued for 2 recipient(s).');

        $announcement = Announcement::query()->sole();
        $this->assertSame(['customers', route('home'), 2], [$announcement->audience, $announcement->url, $announcement->recipient_count]);
        Queue::assertPushed(SendAdminAnnouncement::class, fn (SendAdminAnnouncement $job): bool => $job->announcementId === $announcement->id && $job->batchUserIds === null);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $administrator->id, 'action' => 'announcement.sent', 'entity_id' => $announcement->id]);

        $this->actingAs($administrator)->get(route('admin.announcements.create'))->assertSee('Big sale')->assertSee('0/2 sent');
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

        $notifier = Mockery::mock(PushNotifier::class);
        $notifier->shouldReceive('sendToUser')
            ->once()
            ->with(Mockery::on(fn (User $user): bool => $user->is($customer)), 'Big sale', 'Everything 20% off.', ['type' => 'announcement']);

        (new SendAdminAnnouncement($announcement->id, [$customer->id]))->handle($notifier);

        Notification::assertSentTo($customer, MarketplaceNotice::class);
        $this->assertSame(1, $announcement->fresh()->sent_count);
        $this->assertTrue($announcement->fresh()->isFinished());
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
