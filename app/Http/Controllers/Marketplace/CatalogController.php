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
        $product = $this->publishedProducts()->with('images')
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

        return view('marketplace.catalog.show', [
            'product' => $product,
            'reviews' => $reviews->paginate(10)->withQueryString(),
            'distribution' => $distribution,
            'reviewFilters' => $reviewFilters,
            'cartItemCount' => $request->user()?->cartItemCount() ?? 0,
        ]);
    }

    private function catalog(Request $request, ?Store $seller = null, bool $isSellerPreview = false): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['relevance', 'newest', 'price_asc', 'price_desc'])],
        ]);
        $search = trim($filters['q'] ?? '');
        $searchTerm = '%'.Str::lower($search).'%';

        $categoryProducts = $this->publishedProducts()
            ->when($seller, fn (Builder $query) => $query->whereBelongsTo($seller));

        $catalogProducts = (clone $categoryProducts)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                ->orWhereRaw('LOWER(description) LIKE ?', [$searchTerm])
                ->orWhereHas('store', fn (Builder $store) => $store
                    ->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                    ->orWhereHas('tenant', fn (Builder $tenant) => $tenant
                        ->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                        ->orWhereHas('members.user', fn (Builder $seller) => $seller->whereRaw('LOWER(name) LIKE ?', [$searchTerm]))))));

        $products = (clone $catalogProducts)
            ->select(['id', 'tenant_id', 'store_id', 'category_id', 'name', 'slug', 'price', 'stock_quantity', 'status', 'created_at'])
            ->with(['thumbnail', 'category:id,name,slug'])
            ->withCount(['reviews as published_reviews_count' => fn (Builder $query) => $query->published()])
            ->withAvg(['reviews as published_reviews_avg_rating' => fn (Builder $query) => $query->published()], 'rating')
            ->withSum(['orderItems as sold_quantity' => fn (Builder $query) => $query
                ->whereHas('sellerOrder', fn (Builder $sellerOrder) => $sellerOrder->where('status', '!=', 'cancelled'))], 'quantity')
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->whereHas('category', fn ($query) => $query->where('slug', $category)));

        match ($filters['sort'] ?? 'relevance') {
            'price_asc' => $products->orderBy('price'),
            'price_desc' => $products->orderByDesc('price'),
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
            'search' => $search,
            'sellerStore' => $seller,
            'isSellerPreview' => $isSellerPreview,
            'categories' => $categories,
            'categoryGroups' => $categoryGroups,
            'uncategorizedCategories' => $uncategorizedCategories,
            'cartItemCount' => $request->user()?->cartItemCount() ?? 0,
            'topProducts' => $seller ? collect() : TopProductQuery::featured(8),
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
