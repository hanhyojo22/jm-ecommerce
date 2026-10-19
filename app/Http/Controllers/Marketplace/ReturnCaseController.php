<?php

namespace App\Http\Controllers\Marketplace;

use App\Actions\ReturnCaseWorkflow;
use App\Http\Controllers\Controller;
use App\Models\ReturnCase;
use App\Models\ReturnCaseEvidence;
use App\Models\SellerOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ReturnCaseController extends Controller
{
    public function store(Request $request, SellerOrder $sellerOrder, ReturnCaseWorkflow $workflow): RedirectResponse
    {
        abort_unless($sellerOrder->order()->where('user_id', $request->user()->id)->exists(), 404);
        $data = $request->validate([
            'reason' => ['required', 'in:damaged,defective,wrong_item,misdescribed'],
            'requested_remedy' => ['required', 'in:refund,replacement'],
            'details' => ['required', 'string', 'min:10', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'array:order_item_id,quantity'],
            'items.*.order_item_id' => ['required', 'ulid'],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['image', 'max:5120'],
        ]);
        $case = $workflow->open($sellerOrder, $request->user(), $data);

        return to_route('returns.show', $case)->with('status', 'Your return request was sent to the seller.');
    }

    public function show(Request $request, ReturnCase $returnCase): View
    {
        abort_unless($returnCase->buyer_id === $request->user()->id, 404);
        $returnCase->load('sellerOrder.order', 'sellerOrder.store', 'items.orderItem', 'events.actor', 'evidence', 'outcome');

        return view('returns.show', ['case' => $returnCase, 'role' => 'buyer']);
    }

    public function update(Request $request, ReturnCase $returnCase, ReturnCaseWorkflow $workflow): RedirectResponse
    {
        abort_unless($returnCase->buyer_id === $request->user()->id, 404);
        $data = $request->validate([
            'action' => ['required', 'in:accept,appeal,handoff,confirm'],
            'note' => ['required_if:action,appeal', 'nullable', 'string', 'min:10', 'max:2000'],
            'proof' => ['required_if:action,handoff', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);
        $workflow->act($returnCase, $request->user(), $data['action'], $data);

        return back()->with('status', 'Return case updated.');
    }

    public function evidence(Request $request, ReturnCase $returnCase, ReturnCaseEvidence $evidence)
    {
        abort_unless($evidence->return_case_id === $returnCase->id, 404);
        abort_unless($returnCase->buyer_id === $request->user()->id || $request->user()->isPlatformAdministrator() || ($request->user()->account_type === 'seller' && $request->user()->isMemberOfTenant($returnCase->sellerOrder->tenant_id)), 404);

        return Storage::disk('chat_attachments')->download($evidence->path, $evidence->original_name);
    }
}
