<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Queries\CatalogQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SavedProductController extends Controller
{
    /**
     * The customer's wishlist. A product the pharmacy later unpublished drops off the list.
     */
    public function index(Request $request): View
    {
        $savedIds = $request->user()->savedProducts()->pluck('products.id');

        return view('marketplace.saved-products.index', [
            'products' => CatalogQuery::publishedProducts()
                ->whereIn('id', $savedIds)
                ->with('thumbnail')
                ->withExists('variants')
                ->get()
                ->sortBy(fn (Product $product): int => $savedIds->search($product->id))
                ->values(),
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless(CatalogQuery::publishedProducts()->whereKey($product->id)->exists(), 404);
        $request->user()->savedProducts()->syncWithoutDetaching([$product->id]);

        return back()->with('status', 'Saved to your wishlist.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $request->user()->savedProducts()->detach($product->id);

        return back()->with('status', 'Removed from your wishlist.');
    }
}
