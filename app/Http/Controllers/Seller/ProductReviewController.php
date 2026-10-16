<?php

namespace App\Http\Controllers\Seller;

use App\Actions\SaveSellerReviewResponse;
use App\Http\Controllers\Controller;
use App\Models\ProductDiscussion;
use App\Models\ProductReview;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductReviewController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $this->tenant($request);
        $storeReviews = ProductReview::query()
            ->whereHas('product', fn (Builder $query) => $query->where('tenant_id', $tenant->id));
        $ratingCounts = (clone $storeReviews)->published()
            ->selectRaw('rating, count(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');
        $reviewCount = $ratingCounts->sum();
        $averageRating = $reviewCount ? $ratingCounts->sum(fn ($count, $rating) => $count * $rating) / $reviewCount : null;
        $reviews = $storeReviews
            ->with(['product.store', 'user:id,name', 'photos', 'responses.user:id,name'])
            ->latest()
            ->paginate(20);
        $storeDiscussions = ProductDiscussion::query()
            ->whereHas('product', fn (Builder $query) => $query->where('tenant_id', $tenant->id))
            ->where('status', 'published')
            ->whereNull('parent_id');
        $discussionCount = (clone $storeDiscussions)->count();
        $discussions = $storeDiscussions
            ->with(['product.store', 'user:id,name', 'replies.user:id,name', 'replies.parent.user:id,name'])
            ->latest()
            ->paginate(10, ['*'], 'discussion_page')
            ->withQueryString();

        return view('seller.reviews.index', compact('tenant', 'reviews', 'ratingCounts', 'reviewCount', 'averageRating', 'discussions', 'discussionCount'));
    }

    public function respond(Request $request, ProductReview $review, SaveSellerReviewResponse $saveResponse): RedirectResponse
    {
        $tenant = $this->tenant($request);
        abort_unless($review->product()->where('tenant_id', $tenant->id)->exists(), 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $saveResponse->handle($review, $request->user(), $data['body'], $request->ip());

        return back()->with('status', 'Your seller response was saved.');
    }

    private function tenant(Request $request): Tenant
    {
        return $request->user()->sellerTenant() ?? abort(403);
    }
}
