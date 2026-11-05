<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\ProductResource;
use App\Http\Resources\Api\V1\ProductReviewResource;
use App\Http\Resources\Api\V1\ProductSummaryResource;
use App\Http\Resources\Api\V1\StoreResource;
use App\Models\Category;
use App\Models\Product;
use App\Queries\CatalogQuery;
use App\Queries\TopProductQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function categories(): JsonResponse
    {
        $counts = CatalogQuery::publishedProducts()
            ->select('category_id', DB::raw('count(*) as product_count'))
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->pluck('product_count', 'category_id');

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->each(fn (Category $category) => $category->setAttribute(
                'product_count',
                (int) $counts->get($category->id, 0),
            ));

        return CategoryResource::collection($categories)->response();
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $perPage = (int) ($filters['per_page'] ?? 12);
        $catalog = CatalogQuery::search($filters, null, $perPage);

        return ProductSummaryResource::collection($catalog['products'])
            ->additional([
                'facets' => [
                    'categories' => CategoryResource::collection($catalog['categories'])->resolve($request),
                    'brands' => $catalog['brands']->values()->all(),
                ],
            ])
            ->response($request);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $search = $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '';

        return response()->json(['items' => CatalogQuery::suggestions($search)->map(function (Product $product): array {
            $price = (string) $product->price;

            if (
                $product->variants_min_price !== null
                && (! $product->variants_using_base_price_exists || bccomp((string) $product->variants_min_price, $price, 2) < 0)
            ) {
                $price = (string) $product->variants_min_price;
            }

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'store_name' => $product->store->name,
                'store_slug' => $product->store->slug,
                'image_url' => $product->thumbnail ? Storage::disk('product_images')->url($product->thumbnail->path) : null,
                'price' => $price,
                'sold_out' => $product->stock_quantity < 1,
            ];
        })->all()]);
    }

    public function top(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'between:1,48'],
        ]);

        return ProductSummaryResource::collection(
            TopProductQuery::paginate($filters['category'] ?? null, (int) ($filters['per_page'] ?? 12)),
        )->response();
    }

    public function stores(Request $request): JsonResponse
    {
        $search = trim($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '');
        $searchTerm = '%'.mb_strtolower($search).'%';

        $stores = CatalogQuery::publicStores()
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
            ->withQueryString();

        return StoreResource::collection($stores)->response();
    }

    public function store(string $store): JsonResponse
    {
        $store = CatalogQuery::publicStores()
            ->withCount([
                'products as public_products_count' => fn (Builder $query) => $query->where('status', 'active'),
            ])
            ->where('slug', $store)
            ->firstOrFail();

        return StoreResource::make($store)->response();
    }

    public function show(Request $request, string $store, string $product): JsonResponse
    {
        $product = $this->detailQuery()
            ->whereHas('store', fn (Builder $query) => $query->where('slug', $store))
            ->where('slug', $product)
            ->firstOrFail();

        $distribution = $product->reviews()
            ->published()
            ->selectRaw('rating, count(*) as aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating');

        $relatedProducts = $this->summaryQuery()
            ->where('products.id', '!=', $product->id)
            ->when($product->category_id, fn (Builder $query) => $query->where('category_id', $product->category_id))
            ->latest()
            ->limit(4)
            ->get();

        $moreFromStore = $this->summaryQuery()
            ->where('products.id', '!=', $product->id)
            ->where('store_id', $product->store_id)
            ->latest()
            ->limit(4)
            ->get();

        return response()->json([
            'data' => [
                ...ProductResource::make($product)->resolve($request),
                'review_summary' => [
                    'count' => (int) ($product->published_reviews_count ?? 0),
                    'average' => round((float) ($product->published_reviews_avg_rating ?? 0), 2),
                    'distribution' => collect([5, 4, 3, 2, 1])
                        ->mapWithKeys(fn (int $rating): array => [$rating => (int) $distribution->get($rating, 0)])
                        ->all(),
                ],
                'related_products' => ProductSummaryResource::collection($relatedProducts)->resolve($request),
                'more_from_store' => ProductSummaryResource::collection($moreFromStore)->resolve($request),
            ],
        ]);
    }

    public function reviews(Request $request, string $store, string $product): JsonResponse
    {
        $product = CatalogQuery::publishedProducts()
            ->whereHas('store', fn (Builder $query) => $query->where('slug', $store))
            ->where('slug', $product)
            ->firstOrFail();

        $filters = $request->validate([
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'photos' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['recent', 'highest', 'lowest'])],
            'per_page' => ['nullable', 'integer', 'between:1,50'],
        ]);

        $reviews = $product->reviews()
            ->published()
            ->with(['user:id,name', 'photos', 'responses.user:id,name', 'orderItem'])
            ->when($filters['rating'] ?? null, fn (Builder $query, int $rating) => $query->where('rating', $rating))
            ->when($request->boolean('photos'), fn (Builder $query) => $query->has('photos'));

        match ($filters['sort'] ?? 'recent') {
            'highest' => $reviews->orderByDesc('rating')->latest(),
            'lowest' => $reviews->orderBy('rating')->latest(),
            default => $reviews->latest(),
        };

        return ProductReviewResource::collection(
            $reviews->paginate((int) ($filters['per_page'] ?? 10))->withQueryString(),
        )->response();
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        // A query string sends "false", which is a non-empty string; normalize it to a real flag
        // before validation so the rule below sees "1" or "0".
        foreach (['in_stock', 'on_sale', 'with_photos'] as $flag) {
            if ($request->has($flag)) {
                $request->merge([$flag => $request->boolean($flag) ? '1' : '0']);
            }
        }

        return $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:255'],
            'seller' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['relevance', 'newest', 'price_asc', 'price_desc', 'top_rated', 'popular'])],
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
            'per_page' => ['nullable', 'integer', 'between:1,48'],
        ]);
    }

    private function detailQuery(): Builder
    {
        return CatalogQuery::publishedProducts()
            ->with([
                'images',
                'variants',
                'category:id,name,slug',
                'store:id,name,slug,status,city,logo_path,description,paymongo_account_id,paymongo_account_status',
            ])
            ->withExists(['variants', 'variants as variants_using_base_price_exists' => fn (Builder $query) => $query->whereNull('price')])
            ->withMin('variants', 'price')
            ->withSum(['orderItems as sold_quantity' => fn (Builder $query) => $query
                ->whereHas('sellerOrder', fn (Builder $sellerOrder) => $sellerOrder->where('status', '!=', 'cancelled'))], 'quantity')
            ->withCount(['reviews as published_reviews_count' => fn (Builder $query) => $query->published()])
            ->withAvg(['reviews as published_reviews_avg_rating' => fn (Builder $query) => $query->published()], 'rating');
    }

    /**
     * The card payload's aggregates, shared by related products and the store strip.
     */
    private function summaryQuery(): Builder
    {
        return CatalogQuery::publishedProducts()
            ->select(['id', 'tenant_id', 'store_id', 'category_id', 'name', 'slug', 'price', 'compare_at_price', 'brand', 'condition', 'free_shipping', 'stock_quantity', 'status', 'created_at'])
            ->with(['thumbnail', 'category:id,name,slug'])
            ->withExists(['variants', 'variants as variants_using_base_price_exists' => fn (Builder $query) => $query->whereNull('price')])
            ->withMin('variants', 'price')
            ->withCount(['reviews as published_reviews_count' => fn (Builder $query) => $query->published()])
            ->withAvg(['reviews as published_reviews_avg_rating' => fn (Builder $query) => $query->published()], 'rating')
            ->withSum(['orderItems as sold_quantity' => fn (Builder $query) => $query
                ->whereHas('sellerOrder', fn (Builder $sellerOrder) => $sellerOrder->where('status', '!=', 'cancelled'))], 'quantity');
    }
}
