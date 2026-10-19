<?php

namespace App\Http\Controllers\Seller;

use App\Actions\ReturnCaseWorkflow;
use App\Http\Controllers\Controller;
use App\Models\ReturnCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReturnCaseController extends Controller
{
    /**
     * One label can cover several statuses, so "waiting on the buyer" is a single
     * choice in the queue filter without listing every status separately.
     *
     * @var array<string, array<int, string>>
     */
    private const STATUS_GROUPS = [
        'needs_action' => ['awaiting_seller', 'awaiting_receipt', 'awaiting_fulfillment'],
        'awaiting_buyer' => ['offered', 'rejected', 'awaiting_confirmation', 'awaiting_return'],
        'with_support' => ['appealed'],
        'closed' => ['completed', 'denied', 'closed'],
    ];

    /** @var array<int, string> */
    private const STATUSES = ['awaiting_seller', 'offered', 'rejected', 'awaiting_return', 'awaiting_receipt', 'awaiting_fulfillment', 'awaiting_confirmation', 'appealed', 'completed', 'denied', 'closed'];

    public function index(Request $request): View
    {
        $tenant = $request->user()->sellerTenant() ?? abort(403);
        $forTenant = fn ($query) => $query->whereHas('sellerOrder', fn ($sellerOrder) => $sellerOrder->where('tenant_id', $tenant->id));
        $filters = $this->filters($request);

        $cases = ReturnCase::query()->tap($forTenant)
            ->when($filters['status'] !== '', fn ($query) => $query->whereIn('status', self::STATUS_GROUPS[$filters['status']] ?? [$filters['status']]))
            ->when($filters['overdue'], fn ($query) => $query->overdue())
            ->when($filters['search'] !== null, function ($query) use ($filters): void {
                $term = '%'.Str::lower($filters['search']).'%';
                $query->where(fn ($search) => $search
                    ->whereHas('sellerOrder.order', fn ($order) => $order->whereRaw('LOWER(number) LIKE ?', [$term]))
                    ->orWhereHas('buyer', fn ($buyer) => $buyer->whereRaw('LOWER(name) LIKE ?', [$term])));
            })
            ->with('sellerOrder.order', 'sellerOrder.store', 'buyer', 'items')
            ->orderByDeadline()->latest()->paginate(20)->withQueryString();

        $counts = [];
        foreach (self::STATUS_GROUPS['needs_action'] as $status) {
            $counts[$status] = ReturnCase::query()->tap($forTenant)->where('status', $status)->count();
        }
        $needsActionCount = array_sum($counts);
        $counts['overdue'] = ReturnCase::query()->tap($forTenant)->overdue()->count();

        return view('returns.index', [
            'cases' => $cases,
            'role' => 'seller',
            'counts' => $counts,
            'filters' => $filters,
            'needsActionCount' => $needsActionCount,
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

    /**
     * Queue filters come from the query string, so anything unrecognised falls back
     * to the unfiltered queue instead of erroring.
     *
     * @return array{search: ?string, status: string, overdue: bool, needs_action: bool}
     */
    private function filters(Request $request): array
    {
        $status = (string) $request->query('status', '');
        if (! in_array($status, array_merge(array_keys(self::STATUS_GROUPS), self::STATUSES), true)) {
            $status = '';
        }
        // Older ?needs_action=1 links land on the same queue as the status filter.
        if ($status === '' && $request->boolean('needs_action')) {
            $status = 'needs_action';
        }

        $search = trim((string) $request->query('search', ''));

        return [
            'search' => $search === '' ? null : $search,
            'status' => $status,
            'overdue' => $request->boolean('overdue'),
            'needs_action' => $request->boolean('needs_action'),
        ];
    }
}
