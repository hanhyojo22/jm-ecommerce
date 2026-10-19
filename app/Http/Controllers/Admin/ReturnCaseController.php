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
        $needsAction = fn ($query) => $query->where(fn ($review) => $review->where('status', 'appealed')->orWhere(fn ($stalled) => $stalled->overdue()));
        $cases = ReturnCase::query()->with('sellerOrder.order', 'sellerOrder.store', 'buyer')
            ->when($request->boolean('needs_action'), $needsAction)->orderByDeadline()->latest()->paginate(20)->withQueryString();

        return view('returns.index', [
            'cases' => $cases,
            'role' => 'admin',
            'needsActionCount' => ReturnCase::query()->tap($needsAction)->count(),
            'needsActionOnly' => $request->boolean('needs_action'),
        ]);
    }

    public function show(Request $request, ReturnCase $returnCase): View
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);
        $returnCase->load('sellerOrder.order', 'sellerOrder.store', 'buyer', 'items.orderItem', 'events.actor', 'evidence.uploader', 'outcome');

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
            'action' => ['required', 'in:takeover,decide,verify,close,reopen'],
            'decision' => ['required_if:action,decide', 'nullable', 'in:deny,refund,replacement'],
            'note' => ['required_if:action,decide,verify,close,reopen', 'nullable', 'string', 'min:10', 'max:2000'],
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
