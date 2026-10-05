<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ProductReview;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductReviewController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $this->tenant($request);
        $reviews = ProductReview::query()
            ->whereHas('product', fn ($query) => $query->where('tenant_id', $tenant->id))
            ->with(['product:id,name,slug', 'user:id,name', 'photos', 'response.user:id,name'])
            ->latest()
            ->paginate(20);

        return view('seller.reviews.index', compact('reviews'));
    }

    public function respond(Request $request, ProductReview $review): RedirectResponse
    {
        $tenant = $this->tenant($request);
        abort_unless($review->product()->where('tenant_id', $tenant->id)->exists(), 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $review->response()->updateOrCreate(['product_review_id' => $review->id], ['user_id' => $request->user()->id, 'body' => $data['body']]);

        AuditLog::query()->create([
            'actor_user_id' => $request->user()->id,
            'action' => 'product_review.response_saved',
            'entity_type' => ProductReview::class,
            'entity_id' => $review->id,
            'new_values' => ['body' => $data['body']],
            'ip_address' => $request->ip(),
        ]);

        return back()->with('status', 'Your seller response was saved.');
    }

    private function tenant(Request $request): Tenant
    {
        return $request->user()->sellerTenant() ?? abort(403);
    }
}
