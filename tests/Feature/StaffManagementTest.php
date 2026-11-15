<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_office_adds_a_branch_pharmacist_by_email(): void
    {
        [$tenant, $branch] = $this->pharmacy('botika');
        $owner = $this->member($tenant, 'owner');

        $this->actingAs($owner)->get(route('seller.staff.index'))->assertOk()->assertSee($owner->email);
        $this->actingAs($owner)->post(route('seller.staff.store'), [
            'email' => 'New.Pharmacist@Example.com',
            'role' => 'staff',
            'store_id' => $branch->id,
            'is_pharmacist' => '1',
        ])->assertRedirect(route('seller.staff.index'));

        $employee = User::query()->where('email', 'new.pharmacist@example.com')->sole();
        $this->assertSame('seller', $employee->account_type);
        $this->assertDatabaseHas('tenant_members', ['tenant_id' => $tenant->id, 'user_id' => $employee->id, 'store_id' => $branch->id, 'role' => 'staff', 'is_pharmacist' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant_member.added', 'actor_user_id' => $owner->id]);
    }

    public function test_an_existing_account_is_never_taken_over_as_staff(): void
    {
        [$tenant] = $this->pharmacy('botika');
        $owner = $this->member($tenant, 'owner');
        $customer = User::factory()->create(['email' => 'shopper@example.com']);

        $this->actingAs($owner)->post(route('seller.staff.store'), ['email' => 'Shopper@Example.com', 'role' => 'staff'])
            ->assertSessionHasErrors('email');

        $this->assertSame('buyer', $customer->fresh()->account_type);
        $this->assertDatabaseMissing('tenant_members', ['user_id' => $customer->id]);
    }

    public function test_branch_staff_cannot_manage_staff(): void
    {
        [$tenant, $branch] = $this->pharmacy('botika');
        $branchManager = $this->member($tenant, 'manager', $branch);

        $this->actingAs($branchManager)->get(route('seller.staff.index'))->assertForbidden();
        $this->actingAs($branchManager)->post(route('seller.staff.store'), ['email' => 'x@example.com', 'role' => 'staff'])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }

    public function test_another_companys_employee_and_branch_are_out_of_reach(): void
    {
        [$tenant] = $this->pharmacy('botika');
        [$otherTenant, $otherBranch] = $this->pharmacy('rival');
        $owner = $this->member($tenant, 'owner');
        $rivalStaff = $this->member($otherTenant, 'staff');
        $rivalMember = $rivalStaff->tenantMemberships()->sole();

        $this->actingAs($owner)->patch(route('seller.staff.update', $rivalMember), ['role' => 'manager'])->assertNotFound();
        $this->actingAs($owner)->delete(route('seller.staff.destroy', $rivalMember))->assertNotFound();
        $this->assertDatabaseHas('tenant_members', ['id' => $rivalMember->id, 'role' => 'staff']);

        $this->actingAs($owner)->post(route('seller.staff.store'), ['email' => 'y@example.com', 'role' => 'staff', 'store_id' => $otherBranch->id])
            ->assertSessionHasErrors('store_id');
        $this->assertDatabaseMissing('users', ['email' => 'y@example.com']);
    }

    public function test_the_owner_and_yourself_cannot_be_changed_or_removed(): void
    {
        [$tenant] = $this->pharmacy('botika');
        $owner = $this->member($tenant, 'owner');
        $manager = $this->member($tenant, 'manager');

        $this->actingAs($manager)->delete(route('seller.staff.destroy', $owner->tenantMemberships()->sole()))->assertSessionHasErrors('member');
        $this->actingAs($manager)->patch(route('seller.staff.update', $manager->tenantMemberships()->sole()), ['role' => 'staff'])->assertSessionHasErrors('member');

        $this->assertSame(['owner', 'manager'], TenantMember::query()->orderByRaw("role = 'manager'")->pluck('role')->all());
    }

    public function test_head_office_changes_and_removes_an_employee(): void
    {
        [$tenant, $branch] = $this->pharmacy('botika');
        $owner = $this->member($tenant, 'owner');
        $staff = $this->member($tenant, 'staff');
        $membership = $staff->tenantMemberships()->sole();

        $this->actingAs($owner)->patch(route('seller.staff.update', $membership), ['role' => 'manager', 'store_id' => $branch->id, 'is_pharmacist' => '1'])->assertRedirect();
        $this->assertDatabaseHas('tenant_members', ['id' => $membership->id, 'role' => 'manager', 'store_id' => $branch->id, 'is_pharmacist' => true]);

        $this->actingAs($owner)->delete(route('seller.staff.destroy', $membership))->assertRedirect();
        $this->assertDatabaseMissing('tenant_members', ['id' => $membership->id]);
        $this->assertSame('buyer', $staff->fresh()->account_type);
    }

    /** @return array{0: Tenant, 1: Store} */
    private function pharmacy(string $slug): array
    {
        $tenant = Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => 'active']);
        $branch = Store::create([
            'tenant_id' => $tenant->id,
            'name' => ucfirst($slug).' Makati',
            'slug' => $slug.'-makati',
            'phone' => '+639171234567',
            'address_line' => '10 Rizal Street',
            'city' => 'Makati',
            'status' => 'active',
        ]);

        return [$tenant, $branch];
    }

    private function member(Tenant $tenant, string $role, ?Store $branch = null): User
    {
        $user = User::factory()->seller()->create(['email_verified_at' => now()]);
        TenantMember::create(['tenant_id' => $tenant->id, 'store_id' => $branch?->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }
}
