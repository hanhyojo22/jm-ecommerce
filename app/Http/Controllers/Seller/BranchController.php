<?php

namespace App\Http\Controllers\Seller;

use App\Actions\OpenBranch;
use App\Actions\SetDeliveryFee;
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
            'tenant' => $tenant,
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

    public function updateDeliveryFee(Request $request, SetDeliveryFee $setDeliveryFee): RedirectResponse
    {
        $tenant = $this->headOffice($request);
        $data = $request->validate([
            'delivery_fee' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999.99'],
            'free_delivery_minimum' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999.99'],
        ]);

        $setDeliveryFee->handle(
            $request->user(),
            $tenant,
            bcadd((string) $data['delivery_fee'], '0', 2),
            isset($data['free_delivery_minimum']) ? bcadd((string) $data['free_delivery_minimum'], '0', 2) : null,
            $request->ip(),
        );

        return to_route('seller.branches.index')->with('status', 'Delivery fee saved. New orders use it.');
    }

    private function headOffice(Request $request): Tenant
    {
        return $request->user()->headOfficeTenant() ?? abort(403);
    }
}
