<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ApproveSellerApplication;
use App\Actions\RejectSellerApplication;
use App\Actions\SuspendStore;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SellerApplication;
use App\Models\SellerDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SellerApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SellerApplication::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,pending,submitted,under_review,more_information_required,approved,rejected,suspended'],
            'seller_type' => ['nullable', 'in:individual,business'],
            'submitted_from' => ['nullable', 'date'],
            'submitted_to' => ['nullable', 'date', 'after_or_equal:submitted_from'],
        ]);

        return view('admin.seller-applications.index', [
            'applications' => SellerApplication::query()
                ->with('user:id,name,email')
                ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                    $search = '%'.mb_strtolower($search).'%';

                    $query->where(function (Builder $applicationQuery) use ($search): void {
                        $applicationQuery->whereRaw('LOWER(store_name) LIKE ?', [$search])
                            ->orWhereRaw('LOWER(seller_name) LIKE ?', [$search])
                            ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                                $userQuery->whereRaw('LOWER(name) LIKE ?', [$search])
                                    ->orWhereRaw('LOWER(email) LIKE ?', [$search]);
                            });
                    });
                })
                ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
                ->when($filters['seller_type'] ?? null, fn (Builder $query, string $type): Builder => $query->where('seller_type', $type))
                ->when($filters['submitted_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('submitted_at', '>=', $date))
                ->when($filters['submitted_to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('submitted_at', '<=', $date))
                ->orderByRaw("CASE status WHEN 'submitted' THEN 0 WHEN 'pending' THEN 0 WHEN 'under_review' THEN 0 WHEN 'approved' THEN 1 WHEN 'rejected' THEN 2 ELSE 3 END")
                ->latest('submitted_at')
                ->paginate(16)
                ->withQueryString(),
            'filters' => $filters,
            'statusCounts' => SellerApplication::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
        ]);
    }

    public function show(SellerApplication $sellerApplication): View
    {
        $this->authorize('view', $sellerApplication);

        return view('admin.seller-applications.show', [
            'application' => $sellerApplication->load(['user:id,name,email', 'documents:id,seller_application_id,type,mime_type', 'payoutProfile', 'policyAcceptances', 'reviews', 'statusHistory']),
        ]);
    }

    public function approve(Request $request, SellerApplication $sellerApplication, ApproveSellerApplication $action): RedirectResponse
    {
        $this->authorize('approve', $sellerApplication);
        try {
            $action->handle($request->user(), $sellerApplication, $request->ip());
        } catch (LogicException $exception) {
            return back()->withErrors(['application' => $exception->getMessage()]);
        }

        return to_route('admin.seller-applications.index')->with('status', 'Seller application approved. An active store has been created and now appears in Stores.');
    }

    public function reject(Request $request, SellerApplication $sellerApplication, RejectSellerApplication $action): RedirectResponse
    {
        $this->authorize('approve', $sellerApplication);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        try {
            $action->handle($request->user(), $sellerApplication, $data['reason'], $request->ip());
        } catch (LogicException $exception) {
            return back()->withErrors(['application' => $exception->getMessage()]);
        }

        return to_route('admin.seller-applications.index')->with('status', 'Seller application rejected with a recorded reason.');
    }

    public function review(Request $request, SellerApplication $sellerApplication): RedirectResponse
    {
        $this->authorize('approve', $sellerApplication);
        abort_unless(in_array($sellerApplication->status, ['pending', 'submitted'], true), 403);
        $this->transition($request, $sellerApplication, 'under_review', null);

        return back()->with('status', 'Application is under review.');
    }

    public function note(Request $request, SellerApplication $sellerApplication): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:2000']]);
        DB::transaction(function () use ($request, $sellerApplication, $data): void {
            $sellerApplication->reviews()->create(['reviewer_id' => $request->user()->id, 'action' => 'note', 'note' => $data['note']]);
            AuditLog::query()->create(['actor_user_id' => $request->user()->id, 'action' => 'seller_application.note', 'entity_type' => SellerApplication::class, 'entity_id' => $sellerApplication->id, 'new_values' => ['note' => $data['note']], 'ip_address' => $request->ip()]);
        });

        return back()->with('status', 'Review note saved.');
    }

    public function requestInformation(Request $request, SellerApplication $sellerApplication): RedirectResponse
    {
        $this->authorize('approve', $sellerApplication);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000'], 'steps' => ['required', 'array', 'min:1'], 'steps.*' => ['integer', 'between:1,6']]);
        $steps = array_values(array_unique(array_map('intval', $data['steps'])));
        if (in_array(1, $steps, true)) {
            $steps = array_values(array_unique([...$steps, 2, 5, 6]));
        }
        $this->transition($request, $sellerApplication, 'more_information_required', $data['reason'], $steps);

        return back()->with('status', 'Corrections requested.');
    }

    public function suspend(Request $request, SellerApplication $sellerApplication): RedirectResponse
    {
        abort_unless($request->user()->isPlatformAdministrator() && $sellerApplication->status === 'approved', 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        DB::transaction(function () use ($request, $sellerApplication, $data): void {
            $application = SellerApplication::query()->lockForUpdate()->findOrFail($sellerApplication->id);
            abort_unless($application->status === 'approved', 403);
            app(SuspendStore::class)->handle($request->user(), $application->tenant->store, $data['reason'], $request->ip());
        });

        return back()->with('status', 'Seller suspended.');
    }

    public function document(Request $request, SellerApplication $sellerApplication, SellerDocument $document): StreamedResponse
    {
        abort_unless($request->user()->isPlatformAdministrator() && $document->seller_application_id === $sellerApplication->id, 403);
        abort_unless(Storage::disk('chat_attachments')->exists($document->path), 404);

        return Storage::disk('chat_attachments')->response($document->path, null, ['Content-Type' => $document->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function transition(Request $request, SellerApplication $sellerApplication, string $status, ?string $reason, ?array $steps = null): void
    {
        DB::transaction(function () use ($request, $sellerApplication, $status, $reason, $steps): void {
            $application = SellerApplication::query()->lockForUpdate()->findOrFail($sellerApplication->id);
            $allowed = match ($status) {
                'under_review' => ['pending', 'submitted'],
                'more_information_required' => ['pending', 'submitted', 'under_review'],
                default => [],
            };
            abort_unless(in_array($application->status, $allowed, true), 403);
            $old = $application->status;
            $application->update(['status' => $status, 'review_note' => $reason, 'correction_steps' => $steps, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            $application->reviews()->create(['reviewer_id' => $request->user()->id, 'action' => $status, 'note' => $reason, 'correction_steps' => $steps]);
            $application->statusHistory()->create(['actor_user_id' => $request->user()->id, 'from_status' => $old, 'to_status' => $status, 'reason' => $reason, 'created_at' => now()]);
            AuditLog::query()->create(['actor_user_id' => $request->user()->id, 'action' => 'seller_application.'.$status, 'entity_type' => SellerApplication::class, 'entity_id' => $application->id, 'old_values' => ['status' => $old], 'new_values' => ['status' => $status, 'reason' => $reason], 'ip_address' => $request->ip()]);
        });
    }
}
