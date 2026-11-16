<?php

namespace App\Http\Controllers\Seller;

use App\Actions\SetBranchHours;
use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchHoursController extends Controller
{
    public function edit(Request $request): View
    {
        return view('seller.opening-hours.edit', [
            'branch' => $this->branch($request),
            'branches' => $request->user()->headOfficeTenant()?->stores()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, SetBranchHours $setBranchHours): RedirectResponse
    {
        $branch = $this->branch($request);
        $data = $request->validate([
            'opening_time' => ['nullable', 'required_with:closing_time', 'date_format:H:i'],
            'closing_time' => ['nullable', 'required_with:opening_time', 'date_format:H:i', 'different:opening_time'],
            'prep_minutes' => ['nullable', 'integer', 'between:5,240'],
        ]);

        $setBranchHours->handle($request->user(), $branch, [
            'opening_time' => $data['opening_time'] ?? null,
            'closing_time' => $data['closing_time'] ?? null,
            'prep_minutes' => isset($data['prep_minutes']) ? (int) $data['prep_minutes'] : null,
        ], $request->ip());

        return to_route('seller.opening-hours.edit', ['branch' => $branch->id])->with('status', "Opening hours saved for {$branch->name}.");
    }

    /**
     * A branch's own manager sets its hours; head office picks any of the company's branches,
     * and one outside the company is a 404. Other branch staff cannot.
     */
    private function branch(Request $request): Store
    {
        $member = $request->user()->sellerMembership(['owner', 'manager']) ?? abort(403);

        if (! $member->isHeadOffice()) {
            return $member->store;
        }

        return $request->filled('branch')
            ? $member->tenant->stores()->whereKey($request->string('branch')->toString())->firstOrFail()
            : $member->tenant->store;
    }
}
