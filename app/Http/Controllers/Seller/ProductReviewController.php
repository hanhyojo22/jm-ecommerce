<?php

namespace App\Http\Controllers\Seller;

use App\Actions\SaveSellerReviewResponse;
use App\Http\Controllers\Controller;
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
            ->with(['product.store', 'user:id,name', 'photos', 'responses.user:id,name'])
            ->latest()
            ->paginate(20);

        return view('seller.reviews.index', compact('reviews'));
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
