<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ProductReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductReviewModerationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);
        $filters = $request->validate([
            'review' => ['nullable', 'ulid'],
            'reported' => ['nullable', 'boolean'],
        ]);
        $reviews = ProductReview::query()
            ->with(['product:id,name', 'user:id,name', 'reports.user:id,name'])
            ->when($filters['review'] ?? null, fn ($query, string $reviewId) => $query->whereKey($reviewId))
            ->when($filters['reported'] ?? false, fn ($query) => $query->published()->whereHas('reports'))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.reviews.index', compact('filters', 'reviews'));
    }

    public function moderate(Request $request, ProductReview $review): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'status' => ['required', 'in:published,hidden,removed'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        DB::transaction(function () use ($request, $review, $data): void {
            $review = ProductReview::query()->lockForUpdate()->findOrFail($review->id);
            $oldValues = $review->only(['status', 'moderation_reason', 'moderated_by']);
            $review->update(['status' => $data['status'], 'moderation_reason' => $data['reason'], 'moderated_by' => $request->user()->id]);

            AuditLog::query()->create([
                'actor_user_id' => $request->user()->id,
                'action' => 'product_review.moderated',
                'entity_type' => ProductReview::class,
                'entity_id' => $review->id,
                'old_values' => $oldValues,
                'new_values' => ['status' => $data['status'], 'reason' => $data['reason']],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Review moderation decision recorded.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);
    }
}
