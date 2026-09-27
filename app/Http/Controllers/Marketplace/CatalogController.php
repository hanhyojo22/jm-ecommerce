<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        return $this->catalog($request);
    }

    public function store(Request $request, string $store): View
    {
        $seller = $this->publishedStores()
            ->where('slug', $store)
            ->firstOrFail();

        return $this->catalog($request, $seller);
    }

    public function show(string $store, string $product): View
    {
        return view('marketplace.catalog.show', [
            'product' => $this->publishedProducts()->with('images')
                ->whereHas('store', fn ($query) => $query->where('slug', $store))
                ->where('slug', $product)
                ->firstOrFail(),
            'cartItemCount' => request()->user()?->cartItemCount() ?? 0,
        ]);
    }

    private function catalog(Request $request, ?Store $seller = null): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:255'],
            'seller' => ['nullable', 'string', 'max:255'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            'available' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['relevance', 'newest', 'price_asc', 'price_desc'])],
        ]);
        $search = trim($filters['q'] ?? '');
        $searchTerm = '%'.Str::lower($search).'%';
        $selectedSeller = $seller?->slug ?? ($filters['seller'] ?? null);

        $products = $this->publishedProducts()
            ->with(['images', 'category'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                ->orWhereRaw('LOWER(description) LIKE ?', [$searchTerm])))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->whereHas('category', fn ($query) => $query->where('slug', $category)))
            ->when($selectedSeller, fn ($query, $storeSlug) => $query->whereHas('store', fn ($query) => $query->where('slug', $storeSlug)))
            ->when($filters['price_min'] ?? null, fn ($query, $minimum) => $query->where('price', '>=', $minimum))
            ->when($filters['price_max'] ?? null, fn ($query, $maximum) => $query->where('price', '<=', $maximum))
            ->when($request->boolean('available'), fn ($query) => $query->where('stock_quantity', '>', 0));

        match ($filters['sort'] ?? 'relevance') {
            'price_asc' => $products->orderBy('price'),
            'price_desc' => $products->orderByDesc('price'),
            'newest', 'relevance' => $products->latest(),
        };

        $categories = Category::query()->where('is_active', true)->orderBy('name')->get();
        $categoryCounts = $this->publishedProducts()
            ->select('category_id', DB::raw('count(*) as product_count'))
            ->groupBy('category_id')
            ->pluck('product_count', 'category_id');
        $categories->each(fn (Category $category) => $category->setAttribute('product_count', $categoryCounts->get($category->id, 0)));
        $priceBounds = $this->publishedProducts()->selectRaw('MIN(price) as minimum, MAX(price) as maximum')->first();

        return view('marketplace.catalog.index', [
            'products' => $products->paginate(12)->withQueryString(),
            'search' => $search,
            'filters' => $filters,
            'sellerStore' => $seller,
            'categories' => $categories,
            'priceBounds' => $priceBounds,
            'sellers' => $this->publishedStores()->orderBy('name')->get(),
            'cartItemCount' => $request->user()?->cartItemCount() ?? 0,
        ]);
    }

    private function publishedProducts(): Builder
    {
        return Product::query()
            ->with('store')
            ->where('status', 'active')
            ->whereHas('store', fn ($query) => $query->where('status', 'active'))
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'));
    }

    private function publishedStores(): Builder
    {
        return Store::query()
            ->where('status', 'active')
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
            ->whereHas('products', fn ($query) => $query->where('status', 'active'));
    }
}
