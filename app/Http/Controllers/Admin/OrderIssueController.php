<?php

namespace App\Http\Controllers\Admin;

use App\Actions\UpdateSellerOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\OrderIssue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderIssueController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', 'in:open,resolved,rejected']]);
        $issues = OrderIssue::query()
            ->with(['buyer:id,name,email', 'sellerOrder.order:id,number,status', 'sellerOrder.store:id,name'])
            ->when($filters['status'] ?? 'open', fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.order-issues.index', compact('filters', 'issues'));
    }

    public function update(Request $request, OrderIssue $orderIssue, UpdateSellerOrderStatus $updateStatus): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:resolved,rejected,cancel_order,cancel_order_courier'],
            'resolution_note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $orderIssue, $updateStatus, $data): void {
            $orderIssue = OrderIssue::query()->with('sellerOrder')->lockForUpdate()->findOrFail($orderIssue->id);

            if ($orderIssue->status !== 'open') {
                throw ValidationException::withMessages(['decision' => 'This request has already been reviewed.']);
            }

            if ($data['decision'] === 'cancel_order_courier' && $orderIssue->type !== 'failed_delivery') {
                throw ValidationException::withMessages(['decision' => 'Only a failed delivery can be put down to the courier.']);
            }

            if (in_array($data['decision'], ['cancel_order', 'cancel_order_courier'], true)) {
                if (! in_array($orderIssue->type, ['cancellation', 'failed_delivery'], true)) {
                    throw ValidationException::withMessages(['decision' => 'Only cancellation and failed delivery requests can cancel an order.']);
                }

                $updateStatus->handle($orderIssue->sellerOrder, 'cancelled', $request->user(), $request->ip());
            }

            $orderIssue->update([
                // A returned parcel counts against the buyer unless support puts it down to the courier.
                'counts_as_refusal' => $orderIssue->type === 'failed_delivery' && $data['decision'] === 'cancel_order',
                'status' => $data['decision'] === 'rejected' ? 'rejected' : 'resolved',
                'resolution_note' => $data['resolution_note'],
                'resolved_by' => $request->user()->id,
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $request->user()->id,
                'action' => 'order_issue.reviewed',
                'entity_type' => OrderIssue::class,
                'entity_id' => $orderIssue->id,
                'old_values' => ['status' => 'open'],
                'new_values' => ['status' => $orderIssue->status, 'decision' => $data['decision']],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Order request updated.');
    }
}
