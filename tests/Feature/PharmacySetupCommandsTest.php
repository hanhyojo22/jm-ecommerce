<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacySetupCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_creates_the_company_with_a_head_office_pharmacist_owner(): void
    {
        $this->artisan('pharmacy:setup', ['company' => 'JMMart Pharmacy', 'owner' => 'Owner@Example.com'])->assertExitCode(0);

        $tenant = Tenant::query()->sole();
        $owner = User::query()->where('email', 'owner@example.com')->sole();
        $this->assertSame(['JMMart Pharmacy', 'active', 'seller'], [$tenant->name, $tenant->status, $owner->account_type]);
        $this->assertDatabaseHas('tenant_members', ['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner', 'store_id' => null, 'is_pharmacist' => true]);
        $this->assertSame($tenant->id, $owner->headOfficeTenant()?->id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.set_up', 'entity_id' => $tenant->id]);
    }

    public function test_setup_promotes_an_existing_customer_account(): void
    {
        $customer = User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('pharmacy:setup', ['company' => 'JMMart Pharmacy', 'owner' => 'owner@example.com'])->assertExitCode(0);

        $this->assertSame('seller', $customer->fresh()->account_type);
        $this->assertSame(1, User::query()->where('email', 'owner@example.com')->count());
    }

    public function test_setup_refuses_an_administrator_or_existing_staff_email(): void
    {
        User::factory()->administrator()->create(['email' => 'admin@example.com']);
        $this->artisan('pharmacy:setup', ['company' => 'JMMart Pharmacy', 'owner' => 'admin@example.com'])->assertExitCode(1);

        $this->artisan('pharmacy:setup', ['company' => 'First', 'owner' => 'owner@example.com'])->assertExitCode(0);
        $this->artisan('pharmacy:setup', ['company' => 'Second', 'owner' => 'owner@example.com'])->assertExitCode(1);

        $this->assertSame(['First'], Tenant::query()->pluck('name')->all());
    }

    public function test_super_admin_command_creates_a_linkable_super_admin(): void
    {
        $this->artisan('admin:super', ['email' => 'Boss@Example.com'])->assertExitCode(0);

        $admin = User::query()->where('email', 'boss@example.com')->sole();
        $this->assertSame(['admin', 'super_admin', true], [$admin->account_type, $admin->platform_role, $admin->is_active]);
        $this->assertNotNull($admin->admin_invited_at);
        $this->assertTrue($admin->isOwner());
    }

    public function test_super_admin_command_promotes_an_admin_and_refuses_pharmacy_staff(): void
    {
        $admin = User::factory()->administrator()->create(['email' => 'admin@example.com']);
        $this->artisan('admin:super', ['email' => 'admin@example.com'])->assertExitCode(0);
        $this->assertSame('super_admin', $admin->fresh()->platform_role);

        $staff = User::factory()->seller()->create(['email' => 'staff@example.com']);
        $tenant = Tenant::query()->create(['name' => 'JMMart Pharmacy', 'slug' => 'jmmart-pharmacy', 'status' => 'active']);
        TenantMember::query()->create(['tenant_id' => $tenant->id, 'user_id' => $staff->id, 'role' => 'staff']);

        $this->artisan('admin:super', ['email' => 'staff@example.com'])->assertExitCode(1);
        $this->assertSame('seller', $staff->fresh()->account_type);
    }
}
