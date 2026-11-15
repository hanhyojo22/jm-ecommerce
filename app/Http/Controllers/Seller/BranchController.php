<?php

namespace App\Http\Controllers\Seller;

use App\Actions\OpenBranch;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $this->headOffice($request);

        return view('seller.branches.index', [
            'branches' => $tenant->stores()->withCount('branchStocks')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->headOffice($request);

        return view('seller.branches.create');
    }

    public function store(Request $request, OpenBranch $openBranch): RedirectResponse
    {
        $tenant = $this->headOffice($request);
        $details = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'address_line' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
        ]);

        $branch = $openBranch->handle($request->user(), $tenant, $details, $request->ip());

        return to_route('seller.store-settings.edit', ['branch' => $branch->id])
            ->with('status', 'Branch added. Set its map pin and delivery radius, then open it to customers.');
    }

    private function headOffice(Request $request): Tenant
    {
        return $request->user()->headOfficeTenant() ?? abort(403);
    }
}
