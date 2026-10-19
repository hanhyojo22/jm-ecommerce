<?php

namespace App\Http\Controllers\Seller;

use App\Actions\ReturnCaseWorkflow;
use App\Http\Controllers\Controller;
use App\Models\ReturnCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReturnCaseController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $request->user()->sellerTenant() ?? abort(403);
        $forTenant = fn ($query) => $query->whereHas('sellerOrder', fn ($sellerOrder) => $sellerOrder->where('tenant_id', $tenant->id));
        $cases = ReturnCase::query()->tap($forTenant)
            ->when($request->boolean('needs_action'), fn ($query) => $query->whereIn('status', ['awaiting_seller', 'awaiting_receipt', 'awaiting_fulfillment']))
            ->with('sellerOrder.order', 'sellerOrder.store', 'buyer')->orderByDeadline()->latest()->paginate(20)->withQueryString();

        return view('returns.index', [
            'cases' => $cases,
            'role' => 'seller',
            'needsActionCount' => ReturnCase::query()->tap($forTenant)->whereIn('status', ['awaiting_seller', 'awaiting_receipt', 'awaiting_fulfillment'])->count(),
            'needsActionOnly' => $request->boolean('needs_action'),
        ]);
    }

    public function show(Request $request, ReturnCase $returnCase): View
    {
        $tenant = $request->user()->sellerTenant() ?? abort(403);
        $case = ReturnCase::query()->whereKey($returnCase)->whereHas('sellerOrder', fn ($query) => $query->where('tenant_id', $tenant->id))
            ->with('sellerOrder.order', 'sellerOrder.store', 'buyer', 'items.orderItem', 'events.actor', 'evidence.uploader', 'outcome')->firstOrFail();

        return view('returns.show', ['case' => $case, 'role' => 'seller']);
    }

    public function update(Request $request, ReturnCase $returnCase, ReturnCaseWorkflow $workflow): RedirectResponse
    {
        $tenant = $request->user()->sellerTenant(['owner', 'manager']) ?? abort(403);
        $case = ReturnCase::query()->whereKey($returnCase)->whereHas('sellerOrder', fn ($query) => $query->where('tenant_id', $tenant->id))->firstOrFail();
        $data = $request->validate([
            'action' => ['required', 'in:reject,offer,receive,refund,ship,restock'],
            'note' => ['required_if:action,reject,offer', 'nullable', 'string', 'min:10', 'max:2000'],
            'remedy' => ['required_if:action,offer', 'nullable', 'in:refund,replacement'],
            'return_waived' => ['nullable', 'boolean'],
            'return_instructions' => ['nullable', 'string', 'max:2000'],
            'return_method' => ['nullable', 'string', 'max:500'],
            'paid_at' => ['required_if:action,refund', 'nullable', 'date', 'before_or_equal:today'],
            'proof' => ['required_if:action,refund', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'reference' => ['required_if:action,ship', 'nullable', 'string', 'max:255'],
            'case_item_id' => ['required_if:action,restock', 'nullable', 'ulid'],
            'quantity' => ['required_if:action,restock', 'nullable', 'integer', 'min:1'],
        ]);
        if ($data['action'] === 'offer' && ! ($data['return_waived'] ?? false) && (blank($data['return_instructions'] ?? null) || blank($data['return_method'] ?? null))) {
            return back()->withErrors(['return_instructions' => 'Provide free return instructions and a no-cost return method.']);
        }
        $workflow->act($case, $request->user(), $data['action'], $data);

        return back()->with('status', 'Return case updated.');
    }
}
