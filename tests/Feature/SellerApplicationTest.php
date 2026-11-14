<?php

namespace Tests\Feature;

use App\Actions\ApproveSellerApplication;
use App\Actions\SuspendStore;
use App\Models\AuditLog;
use App\Models\SellerApplication;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SellerApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_customer_can_start_a_seller_application_draft(): void
    {
        $customer = User::factory()->seller()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($customer)->post(route('seller-application.step', 1), ['seller_type' => 'individual']);

        $response->assertRedirect(route('seller-application.create', ['step' => 2]));
        $this->assertDatabaseHas('seller_applications', [
            'user_id' => $customer->id,
            'seller_type' => 'individual',
            'status' => 'draft',
        ]);
    }

    public function test_a_second_pending_application_is_rejected_without_creating_another_record(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $customer->sellerApplications()->create(['store_name' => 'Northwind Goods', 'status' => 'pending']);
        $this->actingAs($customer)->post(route('seller-application.step', 1), ['seller_type' => 'individual'])
            ->assertSessionHasErrors('application');

        $this->assertDatabaseCount('seller_applications', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_only_a_platform_administrator_can_approve_an_application(): void
    {
        $customer = User::factory()->seller()->create(['email_verified_at' => now()]);
        $application = $customer->sellerApplications()->create([
            'store_name' => 'Northwind Goods',
            'seller_name' => 'Ava Seller',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $this->actingAs($customer)
            ->post(route('admin.seller-applications.approve', $application))
            ->assertForbidden();
    }

    public function test_approval_atomically_creates_an_active_tenant_store_and_owner_membership(): void
    {
        $administrator = User::factory()->administrator()->create();
        $customer = User::factory()->seller()->create(['email_verified_at' => now()]);
        $application = $customer->sellerApplications()->create([
            'store_name' => 'Northwind Goods',
            'seller_name' => 'Ava Seller',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $tenant = app(ApproveSellerApplication::class)->handle($administrator, $application);

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'active']);
        $this->assertDatabaseHas('stores', ['tenant_id' => $tenant->id, 'status' => 'active']);
        $this->assertDatabaseHas('tenant_members', ['tenant_id' => $tenant->id, 'user_id' => $customer->id, 'role' => 'owner']);
        $this->assertDatabaseHas('seller_applications', ['id' => $application->id, 'status' => 'approved']);
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'preferred_workspace' => 'seller']);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $administrator->id, 'action' => 'seller_application.approved']);
        $this->assertSame(1, TenantMember::query()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_approval_copies_the_onboarding_address_shipping_and_location_to_the_store(): void
    {
        $administrator = User::factory()->administrator()->create();
        $customer = User::factory()->seller()->create(['email_verified_at' => now()]);
        $details = [
            'region' => 'NCR',
            'province' => 'Metro Manila',
            'barangay' => 'San Lorenzo',
            'postal_code' => '1223',
            'pickup_address' => 'Warehouse 2, Chino Roces Ave',
            'return_address' => '10 Market Street, Manila',
            'processing_days' => 3,
            'fulfillment_methods' => 'Courier pickup',
            'delivery_areas' => 'Metro Manila',
            'latitude' => 14.5547,
            'longitude' => 121.0244,
            'delivery_radius_km' => 8,
        ];
        $application = $customer->sellerApplications()->create([
            'store_name' => 'Northwind Goods',
            'seller_name' => 'Ava Seller',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => 'pending',
            'submitted_at' => now(),
            ...$details,
        ]);

        $tenant = app(ApproveSellerApplication::class)->handle($administrator, $application);

        $this->assertSame($details, $tenant->store->only(array_keys($details)));
    }

    public function test_location_migration_backfills_existing_stores_from_their_approved_application(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();
        $customer = User::factory()->seller()->create();
        $tenant = Tenant::create(['name' => 'Northwind', 'slug' => 'northwind', 'status' => 'active']);
        $store = Store::create(['tenant_id' => $tenant->id, 'name' => 'Northwind Goods', 'slug' => 'northwind-goods', 'phone' => '+639171234567', 'address_line' => '10 Market Street', 'city' => 'Manila', 'status' => 'active']);
        DB::table('seller_applications')->insert([
            'id' => (string) str()->ulid(),
            'user_id' => $customer->id,
            'tenant_id' => $tenant->id,
            'status' => 'approved',
            'province' => 'Metro Manila',
            'pickup_address' => 'Warehouse 2, Chino Roces Ave',
            'processing_days' => 3,
        ]);

        $this->artisan('migrate')->assertSuccessful();

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'province' => 'Metro Manila', 'pickup_address' => 'Warehouse 2, Chino Roces Ave', 'processing_days' => 3, 'latitude' => null]);
    }

    public function test_approved_seller_cannot_submit_or_receive_a_second_store_application(): void
    {
        $administrator = User::factory()->administrator()->create();
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        $approvedApplication = $seller->sellerApplications()->create([
            'store_name' => 'First Store',
            'seller_name' => 'Ava Seller',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
        app(ApproveSellerApplication::class)->handle($administrator, $approvedApplication);

        $this->actingAs($seller)->post(route('seller-application.store'), [
            'agreements' => array_fill_keys(['seller_agreement', 'terms', 'privacy', 'prohibited_products', 'returns_refunds', 'fees'], '1'),
        ])->assertRedirect(route('seller.dashboard'));

        $this->assertDatabaseCount('seller_applications', 1);
    }

    public function test_suspended_seller_can_open_start_selling_without_a_redirect_loop(): void
    {
        $administrator = User::factory()->administrator()->create();
        $seller = User::factory()->seller()->create(['email_verified_at' => now()]);
        $application = $seller->sellerApplications()->create([
            'store_name' => 'Northwind Goods',
            'seller_name' => 'Ava Seller',
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
        $tenant = app(ApproveSellerApplication::class)->handle($administrator, $application);
        app(SuspendStore::class)->handle($administrator, $tenant->store, 'Repeated policy violations reported by buyers.');

        $this->actingAs($seller)->get(route('seller-application.create'))
            ->assertOk()
            ->assertSee('Your store is currently suspended.')
            ->assertDontSee('Submit application');
    }

    public function test_administrator_sees_pending_first_and_can_filter_application_cards(): void
    {
        $administrator = User::factory()->administrator()->create();
        $pending = $this->application('Pending Seller', 'pending');
        $this->application('Approved Seller', 'approved');
        $this->application('Rejected Seller', 'rejected');

        $this->actingAs($administrator)->get(route('admin.seller-applications.index'))
            ->assertOk()
            ->assertSee('Seller applications')
            ->assertSee('Pending')
            ->assertSee('Approved')
            ->assertSee('Rejected')
            ->assertSeeInOrder(['Pending Seller', 'Approved Seller', 'Rejected Seller'])
            ->assertSee('Review application')
            ->assertDontSee('<table', false)
            ->assertSee(route('admin.seller-applications.show', $pending), false);

        $this->actingAs($administrator)->get(route('admin.seller-applications.index', ['status' => 'rejected']))
            ->assertOk()
            ->assertSee('Rejected Seller')
            ->assertDontSee('Pending Seller')
            ->assertDontSee('Approved Seller');
    }

    public function test_administrator_can_review_and_reject_a_pending_application_with_a_reason(): void
    {
        $administrator = User::factory()->administrator()->create();
        $application = $this->application('Ava Seller', 'pending');

        $this->actingAs($administrator)->get(route('admin.seller-applications.show', $application))
            ->assertOk()
            ->assertSee('Applicant details')
            ->assertSee('Proposed store')
            ->assertSee('Make a decision')
            ->assertSee('Approve application')
            ->assertSee('Reject application');

        $this->actingAs($administrator)->post(route('admin.seller-applications.reject', $application), ['reason' => 'short'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($administrator)->post(route('admin.seller-applications.reject', $application), ['reason' => 'Business documents were incomplete.'])
            ->assertRedirect(route('admin.seller-applications.index'));

        $this->assertDatabaseHas('seller_applications', [
            'id' => $application->id,
            'status' => 'rejected',
            'review_note' => 'Business documents were incomplete.',
        ]);
    }

    public function test_administrator_approval_from_review_flow_creates_store_once_and_returns_to_updated_counts(): void
    {
        $administrator = User::factory()->administrator()->create();
        $application = $this->application('Ava Seller', 'pending');

        $this->actingAs($administrator)->post(route('admin.seller-applications.approve', $application))
            ->assertRedirect(route('admin.seller-applications.index'))
            ->assertSessionHas('status', 'Seller application approved. An active store has been created and now appears in Stores.');

        $application->refresh();
        $this->assertSame('approved', $application->status);
        $this->assertDatabaseHas('stores', ['tenant_id' => $application->tenant_id, 'name' => 'Ava Seller Shop', 'status' => 'active']);
        $this->assertSame(1, SellerApplication::query()->where('id', $application->id)->where('status', 'approved')->count());
    }

    public function test_administrator_cannot_approve_an_application_for_an_inactive_seller(): void
    {
        $administrator = User::factory()->administrator()->create();
        $application = $this->application('Inactive Seller', 'pending');
        $application->user()->update(['is_active' => false]);

        $this->actingAs($administrator)
            ->post(route('admin.seller-applications.approve', $application))
            ->assertSessionHasErrors('application');

        $this->assertDatabaseHas('seller_applications', ['id' => $application->id, 'status' => 'pending']);
        $this->assertDatabaseMissing('tenants', ['name' => 'Inactive Seller Shop']);
    }

    private function application(string $name, string $status): SellerApplication
    {
        $seller = User::factory()->seller()->create(['name' => $name, 'email_verified_at' => now()]);

        return $seller->sellerApplications()->create([
            'store_name' => $name.' Shop',
            'seller_name' => $name,
            'phone' => '+639171234567',
            'address_line' => '10 Market Street',
            'city' => 'Manila',
            'status' => $status,
            'submitted_at' => now(),
        ]);
    }
}
