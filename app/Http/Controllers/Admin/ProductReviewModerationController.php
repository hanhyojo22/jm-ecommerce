<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ProductReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductReviewModerationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('admin.reviews.index', [
            'reviews' => ProductReview::query()->with(['product:id,name', 'user:id,name', 'reports.user:id,name'])->latest()->paginate(30),
        ]);
    }

    public function moderate(Request $request, ProductReview $review): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'status' => ['required', 'in:published,hidden,removed'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
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

        return back()->with('status', 'Review moderation decision recorded.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->isPlatformAdministrator(), 403);
    }
}
