<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductSummaryResource;
use App\Models\Product;
use App\Queries\CatalogQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedProductController extends Controller
{
    /**
     * The customer's wishlist. Unlike the shop, out-of-stock items stay listed: with a branch,
     * stock_quantity is that branch's units (0 when it has none) so the app can say so.
     */
    public function index(Request $request): JsonResponse
    {
        $branchId = $request->validate(['branch' => ['nullable', 'string', 'ulid']])['branch'] ?? null;
        $branch = $branchId === null ? null : CatalogQuery::publicStores()->whereKey($branchId)->firstOrFail();
        $savedIds = $request->user()->savedProducts()->pluck('products.id');

        $products = CatalogQuery::publishedProducts()
            ->whereIn('id', $savedIds)
            ->with(['thumbnail', 'category:id,name,slug'])
            ->withExists(['variants', 'variants as variants_using_base_price_exists' => fn (Builder $query) => $query->whereNull('price')])
            ->withMin('variants', 'price')
            ->when($branch, fn (Builder $query) => $query->withSum(['branchStocks as branch_quantity' => fn (Builder $stock) => $stock->where('store_id', $branch->id)], 'quantity'))
            ->get()
            ->each(fn (Product $product) => $branch === null ? null : $product->setAttribute('branch_quantity', (int) $product->branch_quantity))
            ->sortBy(fn (Product $product): int => $savedIds->search($product->id))
            ->values();

        return response()->json(['data' => ProductSummaryResource::collection($products)->resolve($request)]);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        abort_unless(CatalogQuery::publishedProducts()->whereKey($product->id)->exists(), 404);
        $request->user()->savedProducts()->syncWithoutDetaching([$product->id]);

        return response()->json(['saved' => true], 201);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $request->user()->savedProducts()->detach($product->id);

        return response()->json(['saved' => false]);
    }
}
