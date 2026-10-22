<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ReturnCaseWorkflow;
use App\Http\Controllers\Controller;
use App\Models\ReturnCase;
use App\Models\SellerOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReturnCaseController extends Controller
{
    /**
     * One label can cover several statuses, so "in progress" and "closed" are single
     * choices in the queue filter without listing every status separately.
     *
     * @var array<string, array<int, string>>
     */
    private const STATUS_GROUPS = [
        'open' => ['awaiting_seller', 'offered', 'rejected', 'awaiting_return', 'awaiting_receipt', 'awaiting_fulfillment', 'awaiting_confirmation', 'appealed'],
        'closed' => ['completed', 'denied', 'closed'],
    ];

    /** @var array<int, string> */
    private const STATUSES = ['awaiting_seller', 'offered', 'rejected', 'awaiting_return', 'awaiting_receipt', 'awaiting_fulfillment', 'awaiting_confirmation', 'appealed', 'completed', 'denied', 'closed'];

    public function index(Request $request): View
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);
        $needsAction = fn ($query) => $query->where(fn ($review) => $review->where('status', 'appealed')->orWhere(fn ($stalled) => $stalled->overdue()));
        $filters = $this->filters($request);

        $cases = ReturnCase::query()
            ->when($filters['needs_action'], $needsAction)
            ->when($filters['status'] !== '' && $filters['status'] !== 'needs_action', fn ($query) => $query->whereIn('status', self::STATUS_GROUPS[$filters['status']] ?? [$filters['status']]))
            ->when($filters['overdue'], fn ($query) => $query->overdue())
            ->when($filters['search'] !== null, function ($query) use ($filters): void {
                $term = '%'.Str::lower($filters['search']).'%';
                $query->where(fn ($search) => $search
                    ->whereHas('sellerOrder.order', fn ($order) => $order->whereRaw('LOWER(number) LIKE ?', [$term]))
                    ->orWhereHas('buyer', fn ($buyer) => $buyer->whereRaw('LOWER(name) LIKE ?', [$term]))
                    ->orWhereHas('sellerOrder.store', fn ($store) => $store->whereRaw('LOWER(name) LIKE ?', [$term])));
            })
            ->with('sellerOrder.order', 'sellerOrder.store', 'buyer', 'items.orderItem')
            ->orderByDeadline()->latest()->paginate(20)->withQueryString();

        $counts = [
            'needs_review' => ReturnCase::query()->tap($needsAction)->count(),
            'appealed' => ReturnCase::query()->where('status', 'appealed')->count(),
            'overdue' => ReturnCase::query()->overdue()->count(),
            'open' => ReturnCase::query()->whereIn('status', self::STATUS_GROUPS['open'])->count(),
        ];

        return view('returns.index', [
            'cases' => $cases,
            'role' => 'admin',
            'counts' => $counts,
            'filters' => $filters,
            'needsActionCount' => $counts['needs_review'],
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
            'note' => ['required_if:action,takeover,decide,verify,close,reopen', 'nullable', 'string', 'min:10', 'max:2000'],
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

    /**
     * Queue filters come from the query string, so anything unrecognised falls back
     * to the unfiltered queue instead of erroring.
     *
     * @return array{search: ?string, status: string, overdue: bool, needs_action: bool}
     */
    private function filters(Request $request): array
    {
        $status = (string) $request->query('status', '');
        if ($status !== 'needs_action' && ! in_array($status, array_merge(array_keys(self::STATUS_GROUPS), self::STATUSES), true)) {
            $status = '';
        }
        // The "Needs review" card and the select both ask for the appeals-plus-stalled queue.
        $needsAction = $request->boolean('needs_action') || $status === 'needs_action';

        $search = trim((string) $request->query('search', ''));

        return [
            'search' => $search === '' ? null : $search,
            'status' => $status,
            'overdue' => $request->boolean('overdue'),
            'needs_action' => $needsAction,
        ];
    }
}
