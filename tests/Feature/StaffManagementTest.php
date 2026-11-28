<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private int $clerkInvitationStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();

        // The local .env may hold a live Clerk key; no test may reach Clerk.
        config(['services.clerk.secret_key' => 'sk_test_fake']);
        Http::preventStrayRequests();
        Http::fake(['api.clerk.com/v1/invitations' => fn () => Http::response([], $this->clerkInvitationStatus)]);
    }

    public function test_a_new_employee_is_emailed_an_invitation_that_lapses(): void
    {
        [$tenant, $branch] = $this->pharmacy('botika');
        $owner = $this->member($tenant, 'owner');

        $this->actingAs($owner)->post(route('seller.staff.store'), ['email' => 'New.Clerk@Example.com', 'role' => 'staff', 'store_id' => $branch->id])
            ->assertSessionHas('status', 'Invitation sent to new.clerk@example.com. They have 14 days to sign in with that email.');

        // The link opens the sign-up form, which is what accepts Clerk's invitation ticket.
        Http::assertSent(fn ($request): bool => $request['email_address'] === 'new.clerk@example.com'
            && $request['redirect_url'] === url('/sign-up')
            && $request['expires_in_days'] === TenantMember::INVITATION_DAYS
            && $request['ignore_existing'] === true);

        // Clerk being down never loses the employee; the owner is told to pass the word on.
        $this->clerkInvitationStatus = 500;
        $this->actingAs($owner)->post(route('seller.staff.store'), ['email' => 'second@example.com', 'role' => 'staff', 'store_id' => $branch->id])
            ->assertSessionHas('status', 'Added second@example.com, but the invitation email could not be sent. Remove them and add them again to resend it.');
        $this->assertDatabaseHas('tenant_members', ['user_id' => User::query()->where('email', 'second@example.com')->value('id')]);
    }

    public function test_a_removed_employee_can_be_added_back_but_only_by_their_company(): void
    {
        [$tenant, $branch] = $this->pharmacy('botika');
        [$rivalTenant, $rivalBranch] = $this->pharmacy('rival');
        $owner = $this->member($tenant, 'owner');
        $employee = $this->member($tenant, 'staff', $branch);
        $employee->forceFill(['clerk_user_id' => 'user_employee'])->save();

        $this->actingAs($owner)->delete(route('seller.staff.destroy', $employee->tenantMemberships()->sole()))->assertRedirect();

        $this->actingAs($this->member($rivalTenant, 'owner'))->post(route('seller.staff.store'), ['email' => $employee->email, 'role' => 'staff', 'store_id' => $rivalBranch->id])
            ->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('tenant_members', ['user_id' => $employee->id]);

        $this->actingAs($owner)->post(route('seller.staff.store'), ['email' => strtoupper($employee->email), 'role' => 'manager', 'store_id' => $branch->id])
            ->assertSessionHas('status', "Added {$employee->email} back to the team. They can sign in now.");

        $this->assertDatabaseHas('tenant_members', ['tenant_id' => $tenant->id, 'user_id' => $employee->id, 'role' => 'manager', 'store_id' => $branch->id]);
        $this->assertSame('seller', $employee->fresh()->account_type);
        Http::assertNothingSent();
    }

    public function test_a_stranger_who_claimed_an_unused_place_never_gets_it_back(): void
    {
        [$tenant, $branch] = $this->pharmacy('botika');
        $owner = $this->member($tenant, 'owner');
        $this->actingAs($owner)->post(route('seller.staff.store'), ['email' => 'typo@example.com', 'role' => 'staff', 'store_id' => $branch->id]);
        $placeholder = User::query()->where('email', 'typo@example.com')->sole();

        // Removed before anyone signed in: adding the email again is just a fresh invitation.
        $this->actingAs($owner)->delete(route('seller.staff.destroy', $placeholder->tenantMemberships()->sole()))->assertRedirect();
        $this->actingAs($owner)->post(route('seller.staff.store'), ['email' => 'typo@example.com', 'role' => 'staff', 'store_id' => $branch->id])
            ->assertSessionHas('status', fn (string $status): bool => str_starts_with($status, 'Invitation sent'));

        // Removed again, then a stranger signs in with the address: they stay a customer.
        $this->actingAs($owner)->delete(route('seller.staff.destroy', $placeholder->tenantMemberships()->sole()))->assertRedirect();
        $placeholder->forceFill(['clerk_user_id' => 'user_stranger'])->save();
        $this->actingAs($owner)->post(route('seller.staff.store'), ['email' => 'typo@example.com', 'role' => 'staff', 'store_id' => $branch->id])
            ->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('tenant_members', ['user_id' => $placeholder->id]);
    }

    public function test_head_office_adds_a_branch_pharmacist_by_email(): void
    {
        [$tenant, $branch] = $this->pharmacy('botika');
        $owner = $this->member($tenant, 'owner');

        $this->actingAs($owner)->get(route('seller.staff.index'))->assertOk()->assertSee($owner->email);
        $this->actingAs($owner)->post(route('seller.staff.store'), ['email' => 'no.license@example.com', 'role' => 'staff', 'store_id' => $branch->id, 'is_pharmacist' => '1'])
            ->assertSessionHasErrors(['license_number', 'license_expires_on']);
        $this->actingAs($owner)->post(route('seller.staff.store'), [
            'email' => 'New.Pharmacist@Example.com',
            'role' => 'staff',
            'store_id' => $branch->id,
            'is_pharmacist' => '1',
            'license_number' => '0123456',
            'license_expires_on' => today()->addYears(2)->toDateString(),
        ])->assertRedirect(route('seller.staff.index'));

        $employee = User::query()->where('email', 'new.pharmacist@example.com')->sole();
        $this->assertSame('seller', $employee->account_type);
        $this->assertDatabaseHas('tenant_members', ['tenant_id' => $tenant->id, 'user_id' => $employee->id, 'store_id' => $branch->id, 'role' => 'staff', 'is_pharmacist' => true, 'license_number' => '0123456']);
        $this->assertDatabaseMissing('users', ['email' => 'no.license@example.com']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant_member.added', 'actor_user_id' => $owner->id]);
    }

    public function test_an_existing_account_is_never_taken_over_as_staff(): void
    {
        [$tenant, $branch] = $this->pharmacy('botika');
        $owner = $this->member($tenant, 'owner');
        $customer = User::factory()->create(['email' => 'shopper@example.com']);

        $this->actingAs($owner)->post(route('seller.staff.store'), ['email' => 'Shopper@Example.com', 'role' => 'staff', 'store_id' => $branch->id])
            ->assertSessionHasErrors('email');

        $this->assertSame('buyer', $customer->fresh()->account_type);
        $this->assertDatabaseMissing('tenant_members', ['user_id' => $customer->id]);
    }

    public function test_branch_staff_cannot_manage_staff(): void
    {
        [$tenant, $branch] = $this->pharmacy('botika');
        $branchStaff = $this->member($tenant, 'staff', $branch);

        $this->actingAs($branchStaff)->get(route('seller.staff.index'))->assertForbidden();
        $this->actingAs($branchStaff)->post(route('seller.staff.store'), ['email' => 'x@example.com', 'role' => 'staff', 'store_id' => $branch->id])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }

    public function test_a_branch_manager_staffs_only_their_own_branch(): void
    {
        [$tenant, $branch] = $this->pharmacy('botika');
        $otherBranch = Store::create(['tenant_id' => $tenant->id, 'name' => 'Botika Cebu', 'slug' => 'botika-cebu', 'phone' => '+639171234567', 'address_line' => '2 Colon Street', 'city' => 'Cebu City', 'status' => 'active']);
        $branchManager = $this->member($tenant, 'manager', $branch);
        $otherBranchStaff = $this->member($tenant, 'staff', $otherBranch)->tenantMemberships()->sole();
        $fellowManager = $this->member($tenant, 'manager', $branch)->tenantMemberships()->sole();

        $this->actingAs($branchManager)->get(route('seller.staff.index'))
            ->assertOk()
            ->assertDontSee($otherBranchStaff->user->email);
        $this->actingAs($branchManager)->post(route('seller.staff.store'), ['email' => 'new.clerk@example.com', 'role' => 'staff', 'store_id' => $branch->id])
            ->assertRedirect(route('seller.staff.index'));
        $this->assertDatabaseHas('tenant_members', ['store_id' => $branch->id, 'role' => 'staff', 'user_id' => User::where('email', 'new.clerk@example.com')->value('id')]);

        $this->actingAs($branchManager)->post(route('seller.staff.store'), ['email' => 'boss@example.com', 'role' => 'manager', 'store_id' => $branch->id])->assertSessionHasErrors('store_id');
        $this->actingAs($branchManager)->post(route('seller.staff.store'), ['email' => 'far@example.com', 'role' => 'staff', 'store_id' => $otherBranch->id])->assertSessionHasErrors('store_id');
        $this->actingAs($branchManager)->delete(route('seller.staff.destroy', $otherBranchStaff))->assertSessionHasErrors('member');
        $this->actingAs($branchManager)->delete(route('seller.staff.destroy', $fellowManager))->assertSessionHasErrors('member');
        $this->assertDatabaseMissing('users', ['email' => 'boss@example.com']);
        $this->assertDatabaseHas('tenant_members', ['id' => $otherBranchStaff->id]);
        $this->assertDatabaseHas('tenant_members', ['id' => $fellowManager->id]);
    }

    public function test_staff_must_work_at_a_branch(): void
    {
        [$tenant] = $this->pharmacy('botika');
        $owner = $this->member($tenant, 'owner');

        $this->actingAs($owner)->post(route('seller.staff.store'), ['email' => 'desk@example.com', 'role' => 'staff'])
            ->assertSessionHasErrors('store_id');
        $this->actingAs($owner)->post(route('seller.staff.store'), ['email' => 'desk@example.com', 'role' => 'manager'])
            ->assertRedirect(route('seller.staff.index'));
        $this->assertDatabaseHas('tenant_members', ['store_id' => null, 'role' => 'manager', 'user_id' => User::where('email', 'desk@example.com')->value('id')]);
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

        $this->actingAs($owner)->patch(route('seller.staff.update', $membership), ['role' => 'manager', 'store_id' => $branch->id, 'is_pharmacist' => '1', 'license_number' => '7654321', 'license_expires_on' => today()->addYear()->toDateString()])->assertRedirect();
        $this->assertDatabaseHas('tenant_members', ['id' => $membership->id, 'role' => 'manager', 'store_id' => $branch->id, 'is_pharmacist' => true]);

        $this->actingAs($owner)->delete(route('seller.staff.destroy', $membership))->assertRedirect();
        $this->assertDatabaseMissing('tenant_members', ['id' => $membership->id]);
        $this->assertSame('buyer', $staff->fresh()->account_type);
    }

    public function test_only_the_owner_adds_head_office_people_or_grants_the_pharmacist_role(): void
    {
        [$tenant, $branch] = $this->pharmacy('botika');
        $this->member($tenant, 'owner');
        $headOfficeManager = $this->member($tenant, 'manager');
        $otherHeadOffice = $this->member($tenant, 'manager')->tenantMemberships()->sole();
        $branchPharmacist = $this->member($tenant, 'staff', $branch)->tenantMemberships()->sole();
        $branchPharmacist->update(['is_pharmacist' => true, 'license_number' => '0123456', 'license_expires_on' => today()->addYear()]);

        $this->actingAs($headOfficeManager)->post(route('seller.staff.store'), ['email' => 'hq@example.com', 'role' => 'manager'])
            ->assertSessionHasErrors('store_id');
        $this->actingAs($headOfficeManager)->patch(route('seller.staff.update', $branchPharmacist), ['role' => 'staff'])
            ->assertSessionHasErrors('store_id');
        $this->actingAs($headOfficeManager)->patch(route('seller.staff.update', $otherHeadOffice), ['role' => 'staff', 'store_id' => $branch->id])
            ->assertSessionHasErrors('member');
        $this->actingAs($headOfficeManager)->delete(route('seller.staff.destroy', $otherHeadOffice))->assertSessionHasErrors('member');
        $this->assertDatabaseMissing('users', ['email' => 'hq@example.com']);
        $this->assertDatabaseHas('tenant_members', ['id' => $otherHeadOffice->id, 'role' => 'manager', 'store_id' => null]);

        // Branch staff stay theirs to manage; a pharmacist tick from a manager is ignored and an existing license survives.
        $this->actingAs($headOfficeManager)->post(route('seller.staff.store'), ['email' => 'cashier@example.com', 'role' => 'staff', 'store_id' => $branch->id, 'is_pharmacist' => '1', 'license_number' => '1111111', 'license_expires_on' => today()->addYear()->toDateString()])
            ->assertRedirect(route('seller.staff.index'));
        $this->assertDatabaseHas('tenant_members', ['user_id' => User::query()->where('email', 'cashier@example.com')->value('id'), 'is_pharmacist' => false, 'license_number' => null]);
        $this->actingAs($headOfficeManager)->patch(route('seller.staff.update', $branchPharmacist), ['role' => 'manager', 'store_id' => $branch->id])->assertRedirect();
        $this->assertDatabaseHas('tenant_members', ['id' => $branchPharmacist->id, 'role' => 'manager', 'is_pharmacist' => true, 'license_number' => '0123456']);
    }

    public function test_the_owner_can_update_only_their_own_pharmacist_license(): void
    {
        [$tenant, $branch] = $this->pharmacy('botika');
        $owner = $this->member($tenant, 'owner');
        $membership = $owner->tenantMemberships()->sole();

        $this->actingAs($owner)->patch(route('seller.staff.update', $membership), ['role' => 'staff', 'store_id' => $branch->id, 'is_pharmacist' => '1', 'license_number' => '0123456', 'license_expires_on' => today()->addYear()->toDateString()])
            ->assertRedirect(route('seller.staff.index'));

        $membership->refresh();
        $this->assertSame(['owner', null, '0123456'], [$membership->role, $membership->store_id, $membership->license_number]);
        $this->assertTrue($membership->canReviewPrescriptions());
    }

    public function test_the_owner_hands_the_pharmacy_to_an_employee_who_has_signed_in(): void
    {
        [$tenant, $branch] = $this->pharmacy('botika');
        [$otherTenant] = $this->pharmacy('rival');
        $owner = $this->member($tenant, 'owner');
        $successorUser = $this->member($tenant, 'staff', $branch);
        $successorUser->forceFill(['clerk_user_id' => 'user_successor'])->save();
        $successor = $successorUser->tenantMemberships()->sole();
        $neverSignedIn = $this->member($tenant, 'manager')->tenantMemberships()->sole();
        $rival = $this->member($otherTenant, 'staff')->tenantMemberships()->sole();

        $this->actingAs($successorUser)->post(route('seller.staff.transfer-ownership', $successor), ['confirm_email' => $successorUser->email])->assertForbidden();
        $this->actingAs($owner)->post(route('seller.staff.transfer-ownership', $rival), ['confirm_email' => 'x@example.com'])->assertNotFound();
        $this->actingAs($owner)->post(route('seller.staff.transfer-ownership', $neverSignedIn), ['confirm_email' => $neverSignedIn->user->email])->assertSessionHasErrors('member');
        $this->actingAs($owner)->post(route('seller.staff.transfer-ownership', $successor), ['confirm_email' => 'wrong@example.com'])->assertSessionHasErrors('confirm_email');
        $this->assertSame($owner->id, TenantMember::query()->where('tenant_id', $tenant->id)->where('role', 'owner')->sole()->user_id);

        $this->actingAs($owner)->post(route('seller.staff.transfer-ownership', $successor), ['confirm_email' => strtoupper($successorUser->email)])
            ->assertRedirect(route('seller.staff.index'));

        $this->assertSame($successorUser->id, TenantMember::query()->where('tenant_id', $tenant->id)->where('role', 'owner')->sole()->user_id);
        $this->assertDatabaseHas('tenant_members', ['id' => $successor->id, 'role' => 'owner', 'store_id' => null]);
        $this->assertDatabaseHas('tenant_members', ['user_id' => $owner->id, 'role' => 'manager', 'store_id' => null]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.ownership_transferred', 'actor_user_id' => $owner->id, 'entity_id' => $successor->id]);
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
