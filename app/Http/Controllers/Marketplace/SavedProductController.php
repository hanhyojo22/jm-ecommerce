<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Queries\CatalogQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SavedProductController extends Controller
{
    /**
     * The customer's wishlist. A product the pharmacy later unpublished drops off the list. Stock
     * is the cart branch's, as on the product page, so "In stock" matches what Add to cart can take.
     */
    public function index(Request $request): View
    {
        $savedIds = $request->user()->savedProducts()->pluck('products.id');
        $branchId = CatalogQuery::publicStores()->whereKey($request->user()->cart()->value('store_id'))->value('id');

        return view('marketplace.saved-products.index', [
            'products' => CatalogQuery::publishedProducts()
                ->whereIn('id', $savedIds)
                ->with('thumbnail')
                ->withExists('variants')
                ->when($branchId, fn (Builder $query) => $query->withSum(['branchStocks as branch_quantity' => fn (Builder $stock) => $stock->where('store_id', $branchId)], 'quantity'))
                ->get()
                ->each(fn (Product $product) => $branchId === null ? null : $product->setAttribute('stock_quantity', (int) $product->branch_quantity))
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
