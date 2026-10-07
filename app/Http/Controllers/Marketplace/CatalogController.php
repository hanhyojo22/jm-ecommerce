<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Queries\TopProductQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function home(Request $request): View
    {
        $categoryCounts = $this->publishedProducts()
            ->select('category_id', DB::raw('count(*) as product_count'))
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->pluck('product_count', 'category_id');
        $categoryOrder = [
            'electronics', 'fashion', 'health-beauty', 'home-living', 'food-grocery', 'babies-kids', 'sports-outdoors', 'pet-supplies',
            'automotive', 'toys-collectibles', 'office-school', 'appliances', 'bags-accessories', 'hobbies-crafts', 'books-media', 'jewelry-watches',
            'garden-diy', 'travel-luggage',
        ];
        $categories = Category::query()->where('is_active', true)->get();
        $categories->each(fn (Category $category) => $category->setAttribute('product_count', $categoryCounts->get($category->id, 0)));
        $categories = $categories->sortBy(function (Category $category) use ($categoryOrder): int {
            $position = array_search($category->slug, $categoryOrder, true);

            return $position === false ? 99 : $position;
        })
            ->take(12)
            ->values();

        $newProducts = $this->publishedProducts()
            ->select(['id', 'tenant_id', 'store_id', 'category_id', 'name', 'slug', 'price', 'compare_at_price', 'brand', 'condition', 'free_shipping', 'stock_quantity', 'status', 'created_at'])
            ->with(['thumbnail', 'category:id,name,slug'])
            ->withCount(['reviews as published_reviews_count' => fn (Builder $query) => $query->published()])
            ->withAvg(['reviews as published_reviews_avg_rating' => fn (Builder $query) => $query->published()], 'rating')
            ->withSum(['orderItems as sold_quantity' => fn (Builder $query) => $query
                ->whereHas('sellerOrder', fn (Builder $sellerOrder) => $sellerOrder->where('status', '!=', 'cancelled'))], 'quantity')
            ->latest()
            ->limit(8)
            ->get();
        $bestSellers = TopProductQuery::featured(8);
        $featuredProduct = $bestSellers->first() ?? $newProducts->first();
        $stores = $this->publicStores()
            ->whereHas('products', fn (Builder $query) => $query->where('status', 'active'))
            ->withCount(['products as public_products_count' => fn (Builder $query) => $query->where('status', 'active')])
            ->orderBy('name')
            ->limit(6)
            ->get();

        return view('marketplace.home', [
            'categories' => $categories,
            'newProducts' => $newProducts,
            'bestSellers' => $bestSellers,
            'featuredProduct' => $featuredProduct,
            'stores' => $stores,
            'cartItemCount' => $request->user()?->cartItemCount() ?? 0,
        ]);
    }

    public function index(Request $request): View
    {
        return $this->catalog($request);
    }

    public function topProducts(Request $request): View
    {
        $filters = $request->validate(['category' => ['nullable', 'string', 'max:255']]);
        $category = $filters['category'] ?? null;

        return view('marketplace.catalog.top-products', [
            'products' => TopProductQuery::paginate($category),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']),
            'selectedCategory' => $category,
            'rankingPeriodDays' => TopProductQuery::RANKING_PERIOD_DAYS,
        ]);
    }

    public function stores(Request $request): View
    {
        $search = trim($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '');
        $searchTerm = '%'.Str::lower($search).'%';

        return view('marketplace.stores.index', [
            'search' => $search,
            'stores' => $this->publicStores()
                ->with('tenant:id,name')
                ->withCount([
                    'products as public_products_count' => fn (Builder $query) => $query->where('status', 'active'),
                ])
                ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                    ->orWhereHas('tenant', fn (Builder $tenant) => $tenant
                        ->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                        ->orWhereHas('members.user', fn (Builder $seller) => $seller->whereRaw('LOWER(name) LIKE ?', [$searchTerm])))))
                ->orderBy('name')
                ->paginate(12)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request, string $store): View
    {
        $seller = $this->publicStores()
            ->withCount([
                'products as public_products_count' => fn (Builder $query) => $query->where('status', 'active'),
            ])
            ->where('slug', $store)
            ->firstOrFail();

        $sellerTenant = $request->user()?->sellerTenant();

        return $this->catalog($request, $seller, $sellerTenant?->store?->is($seller) ?? false);
    }

    public function show(Request $request, string $store, string $product): View
    {
        $product = $this->publishedProducts()->with(['images', 'category:id,name,slug'])
            ->withCount(['reviews as published_reviews_count' => fn (Builder $query) => $query->published()])
            ->withAvg(['reviews as published_reviews_avg_rating' => fn (Builder $query) => $query->published()], 'rating')
            ->whereHas('store', fn ($query) => $query->where('slug', $store))
            ->where('slug', $product)
            ->firstOrFail();
        $reviewFilters = $request->validate([
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'photos' => ['nullable', 'boolean'],
            'sort_reviews' => ['nullable', Rule::in(['recent', 'highest', 'lowest'])],
        ]);
        $reviews = $product->reviews()->published()
            ->with(['user:id,name', 'photos', 'response.user:id,name', 'orderItem.sellerOrder:id,order_id'])
            ->when($reviewFilters['rating'] ?? null, fn (Builder $query, int $rating) => $query->where('rating', $rating))
            ->when(($reviewFilters['photos'] ?? false), fn (Builder $query) => $query->has('photos'));
        match ($reviewFilters['sort_reviews'] ?? 'recent') {
            'highest' => $reviews->orderByDesc('rating')->latest(),
            'lowest' => $reviews->orderBy('rating')->latest(),
            default => $reviews->latest(),
        };
        $distribution = $product->reviews()->published()->selectRaw('rating, count(*) as aggregate')->groupBy('rating')->pluck('aggregate', 'rating');

        $relatedProducts = $this->publishedProducts()
            ->select(['id', 'tenant_id', 'store_id', 'category_id', 'name', 'slug', 'price', 'compare_at_price', 'brand', 'condition', 'free_shipping', 'stock_quantity', 'status'])
            ->with(['thumbnail', 'store:id,name,slug,status'])
            ->withCount(['reviews as published_reviews_count' => fn (Builder $query) => $query->published()])
            ->withAvg(['reviews as published_reviews_avg_rating' => fn (Builder $query) => $query->published()], 'rating')
            ->where('products.id', '!=', $product->id)
            ->when($product->category_id, fn (Builder $query) => $query->where('category_id', $product->category_id))
            ->latest()
            ->limit(4)
            ->get();
        $sellerProducts = $this->publishedProducts()
            ->select(['id', 'tenant_id', 'store_id', 'category_id', 'name', 'slug', 'price', 'compare_at_price', 'brand', 'condition', 'free_shipping', 'stock_quantity', 'status'])
            ->with(['thumbnail', 'store:id,name,slug,status'])
            ->withCount(['reviews as published_reviews_count' => fn (Builder $query) => $query->published()])
            ->withAvg(['reviews as published_reviews_avg_rating' => fn (Builder $query) => $query->published()], 'rating')
            ->where('products.id', '!=', $product->id)
            ->where('store_id', $product->store_id)
            ->latest()
            ->limit(4)
            ->get();

        return view('marketplace.catalog.show', [
            'product' => $product,
            'reviews' => $reviews->paginate(10)->withQueryString(),
            'distribution' => $distribution,
            'reviewFilters' => $reviewFilters,
            'cartItemCount' => $request->user()?->cartItemCount() ?? 0,
            'relatedProducts' => $relatedProducts,
            'sellerProducts' => $sellerProducts,
        ]);
    }

    private function catalog(Request $request, ?Store $seller = null, bool $isSellerPreview = false): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:255'],
            'seller' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['relevance', 'newest', 'price_asc', 'price_desc', 'top_rated'])],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'price_range' => ['nullable', Rule::in(['under_25000', '25000_100000', 'over_100000'])],
            'in_stock' => ['nullable', 'boolean'],
            'brands' => ['nullable', 'array', 'max:20'],
            'brands.*' => ['string', 'max:100', 'distinct'],
            'conditions' => ['nullable', 'array', 'max:2'],
            'conditions.*' => [Rule::in(['new', 'refurbished']), 'distinct'],
            'free_shipping' => ['nullable', 'boolean'],
            'on_sale' => ['nullable', 'boolean'],
            'min_rating' => ['nullable', 'integer', 'between:1,5'],
            'with_photos' => ['nullable', 'boolean'],
        ]);
        $search = trim($filters['q'] ?? '');
        $searchTerm = '%'.Str::lower($search).'%';

        $categoryProducts = $this->publishedProducts()
            ->when($seller, fn (Builder $query) => $query->whereBelongsTo($seller));

        $catalogProducts = (clone $categoryProducts)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                ->orWhereRaw('LOWER(description) LIKE ?', [$searchTerm])
                ->orWhereRaw('LOWER(brand) LIKE ?', [$searchTerm])
                ->orWhereHas('store', fn (Builder $store) => $store
                    ->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                    ->orWhereHas('tenant', fn (Builder $tenant) => $tenant
                        ->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                        ->orWhereHas('members.user', fn (Builder $seller) => $seller->whereRaw('LOWER(name) LIKE ?', [$searchTerm]))))));

        $products = (clone $catalogProducts)
            ->select(['id', 'tenant_id', 'store_id', 'category_id', 'name', 'slug', 'price', 'compare_at_price', 'brand', 'condition', 'free_shipping', 'stock_quantity', 'status', 'created_at'])
            ->with(['thumbnail', 'category:id,name,slug'])
            ->withCount(['reviews as published_reviews_count' => fn (Builder $query) => $query->published()])
            ->withAvg(['reviews as published_reviews_avg_rating' => fn (Builder $query) => $query->published()], 'rating')
            ->withSum(['orderItems as sold_quantity' => fn (Builder $query) => $query
                ->whereHas('sellerOrder', fn (Builder $sellerOrder) => $sellerOrder->where('status', '!=', 'cancelled'))], 'quantity')
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->whereHas('category', fn ($query) => $query->where('slug', $category)))
            ->when(! $seller && ($filters['seller'] ?? null), fn (Builder $query) => $query->whereHas('store', fn (Builder $store) => $store->where('slug', $filters['seller'])))
            ->when(isset($filters['min_price']) && $filters['min_price'] !== null, fn ($query) => $query->where('price', '>=', $filters['min_price']))
            ->when(isset($filters['max_price']) && $filters['max_price'] !== null, fn ($query) => $query->where('price', '<=', $filters['max_price']))
            ->when(($filters['price_range'] ?? null) === 'under_25000', fn ($query) => $query->where('price', '<', 25000))
            ->when(($filters['price_range'] ?? null) === '25000_100000', fn ($query) => $query->whereBetween('price', [25000, 100000]))
            ->when(($filters['price_range'] ?? null) === 'over_100000', fn ($query) => $query->where('price', '>', 100000))
            ->when(! empty($filters['in_stock']), fn ($query) => $query->where('stock_quantity', '>', 0))
            ->when($filters['brands'] ?? [], fn (Builder $query, array $brands) => $query->whereIn('brand', $brands))
            ->when($filters['conditions'] ?? [], fn (Builder $query, array $conditions) => $query->whereIn('condition', $conditions))
            ->when(! empty($filters['free_shipping']), fn (Builder $query) => $query->where('free_shipping', true))
            ->when(! empty($filters['on_sale']), fn (Builder $query) => $query->whereNotNull('compare_at_price')->whereColumn('compare_at_price', '>', 'price'))
            ->when(! empty($filters['min_rating']), fn ($query) => $query->whereRaw('(SELECT AVG(rating) FROM product_reviews WHERE product_reviews.product_id = products.id AND product_reviews.status = ?) >= ?', ['published', $filters['min_rating']]))
            ->when(! empty($filters['with_photos']), fn (Builder $query) => $query->whereHas('reviews', fn (Builder $review) => $review->published()->whereHas('photos')));

        match ($filters['sort'] ?? 'relevance') {
            'price_asc' => $products->orderBy('price'),
            'price_desc' => $products->orderByDesc('price'),
            'top_rated' => $products
                ->orderByRaw('CASE WHEN (SELECT COUNT(*) FROM product_reviews WHERE product_reviews.product_id = products.id AND product_reviews.status = ?) = 0 THEN 1 ELSE 0 END ASC', ['published'])
                ->orderByDesc('published_reviews_avg_rating')
                ->orderByDesc('published_reviews_count'),
            'newest', 'relevance' => $products->latest(),
        };

        $categoryCounts = (clone $categoryProducts)
            ->select('category_id', DB::raw('count(*) as product_count'))
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->pluck('product_count', 'category_id');
        $categories = Category::query()
            ->where('is_active', true)
            ->when($seller, fn (Builder $query) => $query->whereKey($categoryCounts->keys()))
            ->orderBy('name')
            ->get();
        $categories->each(fn (Category $category) => $category->setAttribute('product_count', $categoryCounts->get($category->id, 0)));
        $categoryOrder = [
            'electronics', 'fashion', 'health-beauty', 'home-living', 'food-grocery', 'babies-kids', 'sports-outdoors', 'pet-supplies',
            'automotive', 'toys-collectibles', 'office-school', 'appliances', 'bags-accessories', 'hobbies-crafts', 'books-media', 'jewelry-watches',
            'garden-diy', 'travel-luggage',
        ];
        $categories = $categories->sortBy(function (Category $category) use ($categoryOrder): int {
            $position = array_search($category->slug, $categoryOrder, true);

            return $position === false ? 99 : $position;
        })->values();
        $categoryGroups = collect([
            'Technology' => ['electronics', 'appliances', 'books-media'],
            'Fashion & Lifestyle' => ['fashion', 'health-beauty', 'bags-accessories', 'jewelry-watches'],
            'Home & Essentials' => ['home-living', 'food-grocery', 'office-school', 'garden-diy'],
            'Family & Pets' => ['babies-kids', 'toys-collectibles', 'pet-supplies'],
            'Sports, Hobbies & Travel' => ['sports-outdoors', 'hobbies-crafts', 'travel-luggage'],
            'Automotive' => ['automotive'],
        ])->map(fn (array $slugs) => $categories->whereIn('slug', $slugs)->values())->filter->isNotEmpty();

        $groupedCategoryIds = $categoryGroups->flatten(1)->pluck('id');
        $uncategorizedCategories = $categories->whereNotIn('id', $groupedCategoryIds)->values();

        return view('marketplace.catalog.index', [
            'products' => $products->paginate(12)->withQueryString(),
            'brands' => (clone $categoryProducts)->whereNotNull('brand')->where('brand', '!=', '')->distinct()->orderBy('brand')->pluck('brand'),
            'search' => $search,
            'sellerStore' => $seller,
            'filterStores' => $seller ? collect() : $this->publicStores()
                ->whereHas('products', fn (Builder $query) => $query->where('status', 'active'))
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'isSellerPreview' => $isSellerPreview,
            'categories' => $categories,
            'categoryGroups' => $categoryGroups,
            'uncategorizedCategories' => $uncategorizedCategories,
            'cartItemCount' => $request->user()?->cartItemCount() ?? 0,
            'activeFilters' => array_filter([
                'min_price' => $filters['min_price'] ?? null,
                'max_price' => $filters['max_price'] ?? null,
                'price_range' => $filters['price_range'] ?? null,
                'in_stock' => ! empty($filters['in_stock']) ? true : null,
                'brands' => $filters['brands'] ?? [],
                'conditions' => $filters['conditions'] ?? [],
                'free_shipping' => ! empty($filters['free_shipping']) ? true : null,
                'on_sale' => ! empty($filters['on_sale']) ? true : null,
                'min_rating' => $filters['min_rating'] ?? null,
                'with_photos' => ! empty($filters['with_photos']) ? true : null,
            ], fn ($value) => $value !== null && $value !== ''),
        ]);
    }

    private function publishedProducts(): Builder
    {
        return Product::query()
            ->with('store:id,name,slug,status')
            ->where('status', 'active')
            ->whereHas('store', fn ($query) => $query->where('status', 'active'))
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'));
    }

    private function publicStores(): Builder
    {
        return Store::query()
            ->where('status', 'active')
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'));
    }
}
