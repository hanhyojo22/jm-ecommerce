<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ReturnCaseWorkflow;
use App\Http\Controllers\Controller;
use App\Models\ReturnCase;
use App\Models\SellerOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReturnCaseController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);
        $cases = ReturnCase::query()->with('sellerOrder.order', 'sellerOrder.store')->latest()->paginate(20);

        // shortcut: overdue cases surface when a queue is opened; schedule escalation if exact deadline alerts become necessary.
        $needsActionCount = ReturnCase::query()->where('status', 'appealed')
            ->orWhere(fn ($query) => $query->where('status', 'awaiting_seller')->where('respond_by', '<', now()))
            ->orWhere(fn ($query) => $query->where('status', 'awaiting_fulfillment')->where('fulfill_by', '<', now()))
            ->orWhere(fn ($query) => $query->where('status', 'awaiting_confirmation')->where('buyer_respond_by', '<', now()))->count();

        return view('returns.index', ['cases' => $cases, 'role' => 'admin', 'needsActionCount' => $needsActionCount]);
    }

    public function show(Request $request, ReturnCase $returnCase): View
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);
        $returnCase->load('sellerOrder.order', 'sellerOrder.store', 'items.orderItem', 'events.actor', 'evidence', 'outcome');

        return view('returns.show', ['case' => $returnCase, 'role' => 'admin']);
    }

    public function store(Request $request, SellerOrder $sellerOrder, ReturnCaseWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);
        $data = $request->validate([
            'reason' => ['required', 'in:damaged,defective,wrong_item,misdescribed'],
            'requested_remedy' => ['required', 'in:refund,replacement'],
            'details' => ['required', 'string', 'min:10', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'array:order_item_id,quantity'],
            'items.*.order_item_id' => ['required', 'ulid'],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
            'order_issue_id' => ['nullable', 'ulid'],
        ]);
        $case = $workflow->open($sellerOrder, $request->user(), $data, true);

        return to_route('admin.returns.show', $case)->with('status', 'Late claim opened for review.');
    }

    public function update(Request $request, ReturnCase $returnCase, ReturnCaseWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);
        $data = $request->validate([
            'action' => ['required', 'in:takeover,decide,verify'],
            'decision' => ['required_if:action,decide', 'nullable', 'in:deny,refund,replacement'],
            'note' => ['required_if:action,decide,verify', 'nullable', 'string', 'min:10', 'max:2000'],
            'return_waived' => ['nullable', 'boolean'],
            'return_instructions' => ['nullable', 'string', 'max:2000'],
            'return_method' => ['nullable', 'string', 'max:500'],
        ]);
        if ($data['action'] === 'decide' && $data['decision'] !== 'deny' && ! ($data['return_waived'] ?? false) && (blank($data['return_instructions'] ?? null) || blank($data['return_method'] ?? null))) {
            return back()->withErrors(['return_instructions' => 'Provide free return instructions and a no-cost return method.']);
        }
        $workflow->act($returnCase, $request->user(), $data['action'], $data);

        return back()->with('status', 'Return case updated.');
    }
}
