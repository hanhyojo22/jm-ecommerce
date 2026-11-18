<?php

namespace App\Http\Controllers\Seller;

use App\Actions\AddStaffMember;
use App\Actions\ChangeStaffMember;
use App\Actions\RemoveStaffMember;
use App\Actions\TransferOwnership;
use App\Http\Controllers\Controller;
use App\Models\TenantMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        // A branch manager sees and staffs only their own branch.
        $branchId = $actor->isHeadOffice() ? null : $actor->store_id;

        return view('seller.staff.index', [
            'members' => $actor->tenant->members()->with(['user', 'store'])->when($branchId, fn ($query, string $id) => $query->where('store_id', $id))->orderByRaw("CASE role WHEN 'owner' THEN 0 WHEN 'manager' THEN 1 ELSE 2 END")->oldest()->get(),
            'branches' => $actor->tenant->stores()->when($branchId, fn ($query, string $id) => $query->whereKey($id))->orderBy('name')->get(['id', 'name']),
            'isOwner' => $actor->isOwner(),
            'canAssignManagers' => $actor->isHeadOffice(),
        ]);
    }

    public function store(Request $request, AddStaffMember $addStaffMember): RedirectResponse
    {
        $actor = $this->actor($request);
        $tenant = $actor->tenant;
        $details = [...$request->validate(['email' => ['required', 'email', 'max:255'], ...$this->assignmentRules($actor)]), ...$this->pharmacistDetails($request, $actor)];
        $details['store_id'] ??= null;
        AddStaffMember::assertOwnBranch($tenant, $details['store_id']);
        $this->assertMayAssign($actor, $details['role'], $details['store_id']);

        $addStaffMember->handle($request->user(), $tenant, $details, $request->ip());

        return to_route('seller.staff.index')->with('status', "Added {$details['email']}. They sign in with that email to start.");
    }

    public function update(Request $request, TenantMember $member, ChangeStaffMember $changeStaffMember): RedirectResponse
    {
        $actor = $this->actor($request);
        $tenant = $actor->tenant;
        $member = $tenant->members()->whereKey($member)->firstOrFail();

        // The owner keeps their role and branch; this only updates their own pharmacist license.
        if ($actor->isOwner() && $member->is($actor)) {
            $request->validate($this->pharmacistRules());
            $changeStaffMember->handle($request->user(), $member, ['role' => 'owner', 'store_id' => null, ...$this->pharmacistDetails($request, $actor)], $request->ip());

            return to_route('seller.staff.index')->with('status', 'Your pharmacist license was updated.');
        }

        $this->assertMayManage($actor, $member);
        $details = [...$request->validate($this->assignmentRules($actor)), ...$this->pharmacistDetails($request, $actor, $member)];
        $details['store_id'] ??= null;
        AddStaffMember::assertOwnBranch($tenant, $details['store_id']);
        $this->assertMayAssign($actor, $details['role'], $details['store_id']);

        $changeStaffMember->handle($request->user(), $member, $details, $request->ip());

        return to_route('seller.staff.index')->with('status', 'Employee updated.');
    }

    public function destroy(Request $request, TenantMember $member, RemoveStaffMember $removeStaffMember): RedirectResponse
    {
        $actor = $this->actor($request);
        $member = $actor->tenant->members()->whereKey($member)->firstOrFail();
        $this->assertMayManage($actor, $member);

        $removeStaffMember->handle($request->user(), $member, $request->ip());

        return to_route('seller.staff.index')->with('status', 'Employee removed.');
    }

    public function transferOwnership(Request $request, TenantMember $member, TransferOwnership $transferOwnership): RedirectResponse
    {
        $tenant = $this->actor($request)->tenant;
        abort_unless($this->actor($request)->isOwner(), 403);
        $member = $tenant->members()->with('user')->whereKey($member)->firstOrFail();
        $request->validate(['confirm_email' => ['required', 'string']]);

        // Typing the new owner's email is the deliberate step, like Shopify's password check.
        if (strcasecmp(trim($request->string('confirm_email')->toString()), (string) $member->user?->email) !== 0) {
            throw ValidationException::withMessages(['confirm_email' => 'Type the new owner’s email exactly to confirm.']);
        }

        $transferOwnership->handle($request->user(), $member, $request->ip());

        return to_route('seller.staff.index')->with('status', "{$member->user->email} now owns the pharmacy. You stay on as a head-office manager.");
    }

    /** @return array<string, list<string>> */
    private function assignmentRules(TenantMember $actor): array
    {
        return [
            'role' => ['required', 'in:manager,staff'],
            'store_id' => ['nullable', 'string'],
            ...($actor->isOwner() ? $this->pharmacistRules() : []),
        ];
    }

    /** @return array<string, list<string>> */
    private function pharmacistRules(): array
    {
        return [
            'is_pharmacist' => ['nullable', 'boolean'],
            'license_number' => ['nullable', 'required_if_accepted:is_pharmacist', 'regex:/^\d{1,20}$/'],
            'license_expires_on' => ['nullable', 'required_if_accepted:is_pharmacist', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * Only the owner grants or changes the pharmacist role. Anyone else keeps the employee's current
     * pharmacist details (none for a new employee), so moving a pharmacist between branches keeps their license.
     *
     * @return array{is_pharmacist: bool, license_number: ?string, license_expires_on: ?string}
     */
    private function pharmacistDetails(Request $request, TenantMember $actor, ?TenantMember $member = null): array
    {
        if (! $actor->isOwner()) {
            return [
                'is_pharmacist' => (bool) $member?->is_pharmacist,
                'license_number' => $member?->license_number,
                'license_expires_on' => $member?->license_expires_on?->toDateString(),
            ];
        }

        $isPharmacist = $request->boolean('is_pharmacist');

        return [
            'is_pharmacist' => $isPharmacist,
            'license_number' => $isPharmacist ? $request->string('license_number')->toString() : null,
            'license_expires_on' => $isPharmacist ? $request->string('license_expires_on')->toString() : null,
        ];
    }

    /**
     * Head-office people see every branch, so only the owner may add them or move someone there,
     * and only as a manager: staff fill orders and count stock, which needs a branch. A branch
     * manager staffs their own branch only.
     */
    private function assertMayAssign(TenantMember $actor, string $role, ?string $storeId): void
    {
        if ($storeId === null && $role === 'staff') {
            throw ValidationException::withMessages(['store_id' => 'Staff work at a branch. Choose one, or make them a manager.']);
        }

        if ($storeId === null && ! $actor->isOwner()) {
            throw ValidationException::withMessages(['store_id' => 'Only the owner can add people to head office. Choose a branch.']);
        }

        if (! $actor->isHeadOffice() && ($role !== 'staff' || $storeId !== $actor->store_id)) {
            throw ValidationException::withMessages(['store_id' => 'Branch managers add staff to their own branch only.']);
        }
    }

    private function assertMayManage(TenantMember $actor, TenantMember $member): void
    {
        if ($member->isHeadOffice() && ! $actor->isOwner()) {
            throw ValidationException::withMessages(['member' => 'Only the owner can change or remove head-office people.']);
        }

        if (! $actor->isHeadOffice() && ($member->role !== 'staff' || $member->store_id !== $actor->store_id)) {
            throw ValidationException::withMessages(['member' => 'Branch managers change staff at their own branch only.']);
        }
    }

    private function actor(Request $request): TenantMember
    {
        return $request->user()->sellerMembership(['owner', 'manager']) ?? abort(403);
    }
}
