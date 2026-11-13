<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Queries\CatalogQuery;
use App\Queries\RecommendationQuery;
use App\Queries\TopProductQuery;
use App\Support\CookiePreferences;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogController extends Controller
{
    private const NEW_ARRIVALS_PAGE_SIZE = 12;

    public function home(Request $request): View|JsonResponse
    {
        $cursor = null;
        if ($request->has('new_cursor')) {
            $encodedCursor = $request->validate(['new_cursor' => ['required', 'string', 'max:512']])['new_cursor'];
            $cursor = Cursor::fromEncoded($encodedCursor);
            $parameters = $cursor?->toArray() ?? [];
            abort_unless(count($parameters) === 3
                && is_string($parameters['created_at'] ?? null)
                && is_string($parameters['id'] ?? null)
                && ($parameters['_pointsToNextItems'] ?? null) === true, 422);
        }

        if ($request->expectsJson() && $cursor !== null) {

            $products = $this->homeProducts()
                ->latest()
                ->orderByDesc('id')
                ->cursorPaginate(self::NEW_ARRIVALS_PAGE_SIZE, ['*'], 'new_cursor', $cursor)
                ->withPath(route('home', absolute: false))
                ->fragment('new-arrivals');

            return response()->json([
                'html' => view('marketplace.catalog._new-arrival-cards', ['products' => $products])->render(),
                'next_url' => $products->nextPageUrl(),
            ]);
        }

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
            ->filter(fn (Category $category): bool => $category->product_count > 0)
            ->take(12)
            ->values();

        $homeProducts = $this->homeProducts();
        $newProducts = (clone $homeProducts)
            ->latest()
            ->orderByDesc('id')
            ->cursorPaginate(self::NEW_ARRIVALS_PAGE_SIZE, ['*'], 'new_cursor', $cursor)
            ->withPath(route('home', absolute: false))
            ->fragment('new-arrivals');
        $bestSellers = TopProductQuery::featured(6);
        // Trending keeps the real top sellers; the deals band shows the next-largest drops instead of repeating them.
        $flashDeals = (clone $homeProducts)
            ->whereNotNull('compare_at_price')
            ->whereColumn('compare_at_price', '>', 'price')
            ->where('stock_quantity', '>', 0)
            ->whereNotIn('products.id', $bestSellers->pluck('id'))
            ->orderByRaw('(compare_at_price - price) / compare_at_price desc')
            ->orderByDesc('id')
            ->limit(6)
            ->get();
        $featuredProduct = $flashDeals->first() ?? $bestSellers->first() ?? $newProducts->first();

        $recentProductIds = CookiePreferences::fromRequest($request)['functional'] ?? false
            ? $request->session()->get('marketplace.recently_viewed', [])
            : [];
        $recentlyViewed = $recentProductIds === [] ? collect() : (clone $homeProducts)
            ->whereKey($recentProductIds)
            ->get()
            ->sortBy(fn (Product $product): int => array_search($product->id, $recentProductIds, true))
            ->values();
        $shownProductIds = $flashDeals->pluck('id')->merge($bestSellers->pluck('id'))->merge($newProducts->getCollection()->pluck('id'));
        $recommendedProducts = RecommendationQuery::forRequest($request, limit: 30)->whereNotIn('id', $shownProductIds)->values();
        $recommendedProducts = $recommendedProducts->count() >= 4 ? $recommendedProducts->take(6) : collect();

        $stores = $this->publicStores()
            ->whereHas('products', fn (Builder $query) => $query->where('status', 'active'))
            ->withCount([
                'products as public_products_count' => fn (Builder $query) => $query->where('status', 'active'),
                'products as on_sale_products_count' => fn (Builder $query) => $query->where('status', 'active')->whereColumn('compare_at_price', '>', 'price'),
            ])
            ->orderByDesc('public_products_count')
            ->orderBy('name')
            ->limit(4)
            ->get();

        // Each store's shop window: photographed products first, then best sellers.
        $storeWindowProducts = $stores->mapWithKeys(fn ($store) => [$store->id => (clone $homeProducts)
            ->where('store_id', $store->id)
            ->withExists('thumbnail as has_thumbnail')
            ->orderByDesc('has_thumbnail')
            ->orderByDesc('sold_quantity')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->each->setRelation('store', $store)]);

        return view('marketplace.home', [
            'categories' => $categories,
            'newProducts' => $newProducts,
            'bestSellers' => $bestSellers,
            'featuredProduct' => $featuredProduct,
            'flashDeals' => $flashDeals,
            'recommendedProducts' => $recommendedProducts,
            'recentlyViewed' => $recentlyViewed,
            'stores' => $stores,
            'storeWindowProducts' => $storeWindowProducts,
            'cartItemCount' => $request->user()?->cartItemCount() ?? 0,
        ]);
    }

    private function homeProducts(): Builder
    {
        return $this->publishedProducts()
            ->select(['id', 'tenant_id', 'store_id', 'category_id', 'name', 'slug', 'price', 'compare_at_price', 'brand', 'condition', 'free_shipping', 'stock_quantity', 'status', 'created_at'])
            ->with(['thumbnail', 'category:id,name,slug'])
            ->withExists(['variants', 'variants as variants_using_base_price_exists' => fn (Builder $query) => $query->whereNull('price')])
            ->withMin('variants', 'price')
            ->withCount(['reviews as published_reviews_count' => fn (Builder $query) => $query->published()])
            ->withAvg(['reviews as published_reviews_avg_rating' => fn (Builder $query) => $query->published()], 'rating')
            ->withSum(['orderItems as sold_quantity' => fn (Builder $query) => $query
                ->whereHas('sellerOrder', fn (Builder $sellerOrder) => $sellerOrder->where('status', '!=', 'cancelled'))], 'quantity');
    }

    public function index(Request $request): View
    {
        return $this->catalog($request);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $search = $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '';

        return response()->json(['items' => CatalogQuery::suggestions($search)->map(function (Product $product): array {
            $price = (string) $product->price;
            if ($product->variants_min_price !== null && (! $product->variants_using_base_price_exists || bccomp((string) $product->variants_min_price, $price, 2) < 0)) {
                $price = (string) $product->variants_min_price;
            }

            return [
                'name' => $product->name,
                'image' => $product->thumbnail ? Storage::disk('product_images')->url($product->thumbnail->path) : null,
                'price' => '₱'.number_format((float) $price, 2),
                'store' => $product->store->name,
                'sold_out' => $product->stock_quantity < 1,
                'url' => route('catalog.show', [$product->store->slug, $product->slug]),
            ];
        })->all()]);
    }

    public function clearRecentlyViewed(Request $request): RedirectResponse
    {
        RecommendationQuery::clearHistory($request);

        return to_route('home');
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
        $product = $this->publishedProducts()->with(['images', 'variants', 'category:id,name,slug', 'store:id,name,slug,status,city,logo_path'])
            ->withSum(['orderItems as sold_quantity' => fn (Builder $query) => $query
                ->whereHas('sellerOrder', fn (Builder $sellerOrder) => $sellerOrder->where('status', '!=', 'cancelled'))], 'quantity')
            ->withExists(['variants', 'variants as variants_using_base_price_exists' => fn (Builder $query) => $query->whereNull('price')])
            ->withMin('variants', 'price')
            ->withCount(['reviews as published_reviews_count' => fn (Builder $query) => $query->published()])
            ->withAvg(['reviews as published_reviews_avg_rating' => fn (Builder $query) => $query->published()], 'rating')
            ->whereHas('store', fn ($query) => $query->where('slug', $store))
            ->where('slug', $product)
            ->firstOrFail();
        if (CookiePreferences::fromRequest($request)['functional'] ?? false) {
            $recentlyViewed = $request->session()->get('marketplace.recently_viewed', []);
            $request->session()->put('marketplace.recently_viewed', array_slice(array_values(array_unique([$product->id, ...$recentlyViewed])), 0, 8));
        }
        $reviewFilters = $request->validate([
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'photos' => ['nullable', 'boolean'],
            'sort_reviews' => ['nullable', Rule::in(['recent', 'highest', 'lowest'])],
        ]);
        $reviews = $product->reviews()->published()
            ->with(['user:id,name', 'photos', 'responses.user:id,name', 'orderItem.sellerOrder:id,order_id'])
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
            ->withExists(['variants', 'variants as variants_using_base_price_exists' => fn (Builder $query) => $query->whereNull('price')])
            ->withMin('variants', 'price')
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
            ->withExists(['variants', 'variants as variants_using_base_price_exists' => fn (Builder $query) => $query->whereNull('price')])
            ->withMin('variants', 'price')
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
            'discussions' => $product->discussions()->where('status', 'published')->whereNull('parent_id')
                ->with(['user:id,name', 'parent.user:id,name', 'replies.user:id,name', 'replies.parent.user:id,name'])
                ->orderBy('created_at')->orderBy('id')
                ->paginate(20, ['*'], 'discussion_page'),
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
            'in_stock' => ['nullable', 'boolean'],
            'brands' => ['nullable', 'array', 'max:20'],
            'brands.*' => ['string', 'max:100', 'distinct'],
            'conditions' => ['nullable', 'array', 'max:2'],
            'conditions.*' => [Rule::in(['new', 'refurbished']), 'distinct'],
            'on_sale' => ['nullable', 'boolean'],
            'min_rating' => ['nullable', 'integer', 'between:1,5'],
            'with_photos' => ['nullable', 'boolean'],
        ]);
        $catalog = CatalogQuery::search($filters, $seller);
        $search = trim($filters['q'] ?? '');
        RecommendationQuery::rememberSearch($request, $search);
        $recommendedProducts = $seller === null && $search !== ''
            ? RecommendationQuery::forRequest($request, $search, 16)
                ->whereNotIn('id', $catalog['products']->getCollection()->pluck('id'))->take(4)->values()
            : collect();
        $categories = $catalog['categories'];
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
            'products' => $catalog['products'],
            'recommendedProducts' => $recommendedProducts,
            'brands' => $catalog['brands'],
            'search' => trim($filters['q'] ?? ''),
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
                'in_stock' => ! empty($filters['in_stock']) ? true : null,
                'brands' => $filters['brands'] ?? [],
                'conditions' => $filters['conditions'] ?? [],
                'on_sale' => ! empty($filters['on_sale']) ? true : null,
                'min_rating' => $filters['min_rating'] ?? null,
                'with_photos' => ! empty($filters['with_photos']) ? true : null,
            ], fn ($value) => $value !== null && $value !== ''),
        ]);
    }

    private function publishedProducts(): Builder
    {
        return CatalogQuery::publishedProducts();
    }

    private function publicStores(): Builder
    {
        return CatalogQuery::publicStores();
    }
}
