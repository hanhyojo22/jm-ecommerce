<?php

namespace App\Http\Controllers\Seller;

use App\Actions\AddStaffMember;
use App\Actions\ChangeStaffMember;
use App\Actions\RemoveStaffMember;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $this->headOffice($request);

        return view('seller.staff.index', [
            'members' => $tenant->members()->with(['user', 'store'])->orderByRaw("CASE role WHEN 'owner' THEN 0 WHEN 'manager' THEN 1 ELSE 2 END")->oldest()->get(),
            'branches' => $tenant->stores()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, AddStaffMember $addStaffMember): RedirectResponse
    {
        $tenant = $this->headOffice($request);
        $details = [...$request->validate(['email' => ['required', 'email', 'max:255'], ...$this->assignmentRules()]), 'is_pharmacist' => $request->boolean('is_pharmacist')];
        $details['store_id'] ??= null;
        AddStaffMember::assertOwnBranch($tenant, $details['store_id']);

        $addStaffMember->handle($request->user(), $tenant, $details, $request->ip());

        return to_route('seller.staff.index')->with('status', "Added {$details['email']}. They sign in with that email to start.");
    }

    public function update(Request $request, TenantMember $member, ChangeStaffMember $changeStaffMember): RedirectResponse
    {
        $tenant = $this->headOffice($request);
        $member = $tenant->members()->whereKey($member)->firstOrFail();
        $details = [...$request->validate($this->assignmentRules()), 'is_pharmacist' => $request->boolean('is_pharmacist')];
        $details['store_id'] ??= null;
        AddStaffMember::assertOwnBranch($tenant, $details['store_id']);

        $changeStaffMember->handle($request->user(), $member, $details, $request->ip());

        return to_route('seller.staff.index')->with('status', 'Employee updated.');
    }

    public function destroy(Request $request, TenantMember $member, RemoveStaffMember $removeStaffMember): RedirectResponse
    {
        $tenant = $this->headOffice($request);
        $member = $tenant->members()->whereKey($member)->firstOrFail();

        $removeStaffMember->handle($request->user(), $member, $request->ip());

        return to_route('seller.staff.index')->with('status', 'Employee removed.');
    }

    /** @return array<string, list<string>> */
    private function assignmentRules(): array
    {
        return [
            'role' => ['required', 'in:manager,staff'],
            'store_id' => ['nullable', 'string'],
            'is_pharmacist' => ['nullable', 'boolean'],
        ];
    }

    private function headOffice(Request $request): Tenant
    {
        return $request->user()->headOfficeTenant() ?? abort(403);
    }
}
