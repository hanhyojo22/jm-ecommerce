<?php

namespace App\Queries;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogQuery
{
    private const PUBLIC_FACETS_CACHE_KEY = 'marketplace.catalog.public-facets.v1';

    private const PUBLIC_FACETS_CACHE_SECONDS = 300;

    public static function forgetCachedPublicFacets(): void
    {
        Cache::forget(self::PUBLIC_FACETS_CACHE_KEY);
    }

    /** @return Collection<int, Product> */
    public static function suggestions(string $search): Collection
    {
        $search = Str::lower(trim($search));
        if (Str::length($search) < 2 || ! preg_match('/[\pL\pN]/u', $search)) {
            return collect();
        }

        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
        $prefix = $escaped.'%';
        $postgres = DB::connection()->getDriverName() === 'pgsql';
        $useFullText = $postgres && ! preg_match('/[%_!]/', $search);

        if ($useFullText) {
            preg_match_all('/[\pL\pN]+/u', $search, $matches);
            $wordQuery = implode(' & ', array_map(fn (string $word): string => $word.':*', $matches[0]));
            $wordMatch = "to_tsvector('simple', products.name) @@ to_tsquery('simple', ?)";
            $wordBindings = [$wordQuery];
        } else {
            $wordMatch = "LOWER(products.name) LIKE ? ESCAPE '!'";
            $wordBindings = ['%'.$escaped.'%'];
        }

        $query = self::publishedProducts()
            ->select(['products.id', 'products.store_id', 'products.name', 'products.slug', 'products.price', 'products.stock_quantity'])
            ->with('thumbnail')
            ->withExists(['variants', 'variants as variants_using_base_price_exists' => fn (Builder $query) => $query->whereNull('price')])
            ->withMin('variants', 'price')
            ->where(function (Builder $query) use ($prefix, $wordMatch, $wordBindings, $useFullText, $search): void {
                $query->whereRaw("LOWER(products.name) LIKE ? ESCAPE '!'", [$prefix])
                    ->orWhereRaw($wordMatch, $wordBindings);
                if ($useFullText && Str::length($search) >= 3) {
                    $query->orWhereRaw('LOWER(products.name) %>> ?', [$search]);
                }
            });

        $rank = "CASE WHEN LOWER(products.name) = ? THEN 0 WHEN LOWER(products.name) LIKE ? ESCAPE '!' THEN 1 WHEN {$wordMatch} THEN 2 ELSE 3 END";

        return $query->orderByRaw($rank, [$search, $prefix, ...$wordBindings])
            ->orderBy('products.name')
            ->orderBy('products.id')
            ->limit(8)
            ->get();
    }

    /**
     * @param  array{
     *     q?: string|null,
     *     category?: string|null,
     *     seller?: string|null,
     *     sort?: 'relevance'|'newest'|'price_asc'|'price_desc'|'top_rated'|'popular'|null,
     *     min_price?: string|int|float|null,
     *     max_price?: string|int|float|null,
     *     in_stock?: bool|int|string|null,
     *     brands?: list<string>,
     *     conditions?: list<'new'|'refurbished'>,
     *     on_sale?: bool|int|string|null,
     *     min_rating?: int|null,
     *     with_photos?: bool|int|string|null
     * }  $filters
     * @return array{
     *     products: LengthAwarePaginator,
     *     categories: Collection<int, Category>,
     *     brands: Collection<int, string>
     * }
     */
    public static function search(array $filters, ?Store $seller = null, int $perPage = 12, ?Store $branch = null): array
    {
        $search = trim($filters['q'] ?? '');
        $normalizedSearch = Str::lower($search);
        $escapedSearch = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $normalizedSearch);
        $searchPrefix = $escapedSearch.'%';
        $searchTerm = '%'.$escapedSearch.'%';

        $categoryProducts = self::publishedProducts()
            ->when($seller, fn (Builder $query) => $query->whereBelongsTo($seller))
            // Categories follow the same branch stock as the products below.
            ->when($branch, fn (Builder $query) => $query->whereHas('branchStocks', fn (Builder $stock) => $stock->where('store_id', $branch->id)->where('quantity', '>', 0)));

        $catalogProducts = (clone $categoryProducts)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereRaw("LOWER(products.name) LIKE ? ESCAPE '!'", [$searchTerm])
                ->orWhereRaw("LOWER(products.description) LIKE ? ESCAPE '!'", [$searchTerm])
                ->orWhereRaw("LOWER(products.brand) LIKE ? ESCAPE '!'", [$searchTerm])
                ->orWhereHas('store', fn (Builder $store) => $store
                    ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$searchTerm])
                    ->orWhereHas('tenant', fn (Builder $tenant) => $tenant
                        ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$searchTerm])
                        ->orWhereHas('members.user', fn (Builder $seller) => $seller->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$searchTerm]))))));

        $products = (clone $catalogProducts)
            ->select(['id', 'tenant_id', 'store_id', 'category_id', 'name', 'generic_name', 'dosage_form', 'strength', 'requires_prescription', 'slug', 'price', 'compare_at_price', 'brand', 'condition', 'free_shipping', 'stock_quantity', 'status', 'created_at'])
            ->with(['thumbnail', 'category:id,name,slug'])
            ->withExists(['variants', 'variants as variants_using_base_price_exists' => fn (Builder $query) => $query->whereNull('price')])
            ->withMin('variants', 'price')
            ->withCount(['reviews as published_reviews_count' => fn (Builder $query) => $query->published()])
            ->withAvg(['reviews as published_reviews_avg_rating' => fn (Builder $query) => $query->published()], 'rating')
            ->withSum(['orderItems as sold_quantity' => fn (Builder $query) => $query
                ->whereHas('sellerOrder', fn (Builder $sellerOrder) => $sellerOrder->where('status', '!=', 'cancelled'))], 'quantity')
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->whereHas('category', fn ($query) => $query->where('slug', $category)))
            ->when(! $seller && ($filters['seller'] ?? null), fn (Builder $query) => $query->whereHas('store', fn (Builder $store) => $store->where('slug', $filters['seller'])))
            ->when(isset($filters['min_price']) && $filters['min_price'] !== null, fn ($query) => $query->where('price', '>=', $filters['min_price']))
            ->when(isset($filters['max_price']) && $filters['max_price'] !== null, fn ($query) => $query->where('price', '<=', $filters['max_price']))
            ->when(! empty($filters['in_stock']), fn ($query) => $query->where('stock_quantity', '>', 0))
            ->when($filters['brands'] ?? [], fn (Builder $query, array $brands) => $query->whereIn('brand', $brands))
            ->when($filters['conditions'] ?? [], fn (Builder $query, array $conditions) => $query->whereIn('condition', $conditions))
            ->when(! empty($filters['on_sale']), fn (Builder $query) => $query->whereNotNull('compare_at_price')->whereColumn('compare_at_price', '>', 'price'))
            ->when(! empty($filters['min_rating']), fn ($query) => $query->whereRaw('(SELECT AVG(rating) FROM product_reviews WHERE product_reviews.product_id = products.id AND product_reviews.status = ?) >= ?', ['published', $filters['min_rating']]))
            ->when(! empty($filters['with_photos']), fn (Builder $query) => $query->whereHas('reviews', fn (Builder $review) => $review->published()->whereHas('photos')));

        // shortcut: facets stay company-wide even when a branch narrows the list; scope them per branch if filters show empty choices.
        if ($branch !== null) {
            self::inStockAt($products, $branch);
        }

        match ($filters['sort'] ?? 'relevance') {
            'popular' => TopProductQuery::orderByPopularity($products),
            'price_asc' => $products->orderBy('price'),
            'price_desc' => $products->orderByDesc('price'),
            'top_rated' => $products
                ->orderByRaw('CASE WHEN (SELECT COUNT(*) FROM product_reviews WHERE product_reviews.product_id = products.id AND product_reviews.status = ?) = 0 THEN 1 ELSE 0 END ASC', ['published'])
                ->orderByDesc('published_reviews_avg_rating')
                ->orderByDesc('published_reviews_count'),
            'relevance' => $search === ''
                ? $products->latest()
                : self::orderByRelevance($products, $normalizedSearch, $searchPrefix, $searchTerm),
            'newest' => $products->latest(),
        };

        if ($seller === null && $branch === null) {
            $facets = self::cachedPublicFacets();
            $categoryCounts = collect($facets['category_counts']);
            $brands = collect($facets['brands']);
        } else {
            $categoryCounts = (clone $categoryProducts)
                ->select('category_id', DB::raw('count(*) as product_count'))
                ->whereNotNull('category_id')
                ->groupBy('category_id')
                ->pluck('product_count', 'category_id');
            $brands = (clone $categoryProducts)->whereNotNull('brand')->where('brand', '!=', '')
                ->distinct()->orderBy('brand')->pluck('brand');
        }
        $categories = Category::query()
            ->where('is_active', true)
            ->when($seller !== null || $branch !== null, fn (Builder $query) => $query->whereKey($categoryCounts->keys()))
            ->orderBy('name')
            ->get();
        $categories->each(fn (Category $category) => $category->setAttribute('product_count', $categoryCounts->get($category->id, 0)));

        return [
            'products' => $products->paginate($perPage)->withQueryString(),
            'categories' => $categories,
            'brands' => $brands,
        ];
    }

    private static function orderByRelevance(
        Builder $products,
        string $search,
        string $searchPrefix,
        string $searchTerm,
    ): Builder {
        return $products
            ->orderByRaw(
                <<<'SQL'
                    CASE
                        WHEN LOWER(products.name) = ? THEN 0
                        WHEN LOWER(products.name) LIKE ? ESCAPE '!' THEN 1
                        WHEN LOWER(products.name) LIKE ? ESCAPE '!' THEN 2
                        WHEN LOWER(products.brand) = ? THEN 3
                        WHEN LOWER(products.brand) LIKE ? ESCAPE '!' THEN 4
                        WHEN LOWER(products.description) LIKE ? ESCAPE '!' THEN 5
                        ELSE 6
                    END
                SQL,
                [$search, $searchPrefix, $searchTerm, $search, $searchTerm, $searchTerm],
            )
            ->orderByDesc('products.created_at')
            ->orderBy('products.id');
    }

    /** @return array{category_counts: array<string, int>, brands: list<string>} */
    private static function cachedPublicFacets(): array
    {
        $facets = Cache::get(self::PUBLIC_FACETS_CACHE_KEY);

        if (is_array($facets)) {
            return $facets;
        }

        try {
            return Cache::lock(self::PUBLIC_FACETS_CACHE_KEY.'.lock', 10)->block(2, fn (): array => Cache::remember(
                self::PUBLIC_FACETS_CACHE_KEY,
                self::PUBLIC_FACETS_CACHE_SECONDS,
                self::loadPublicFacets(...),
            ));
        } catch (LockTimeoutException) {
            return self::loadPublicFacets();
        }
    }

    /** @return array{category_counts: array<string, int>, brands: list<string>} */
    private static function loadPublicFacets(): array
    {
        $publicProducts = self::publishedProducts();

        return [
            'category_counts' => (clone $publicProducts)
                ->select('category_id', DB::raw('count(*) as product_count'))
                ->whereNotNull('category_id')
                ->groupBy('category_id')
                ->pluck('product_count', 'category_id')->all(),
            'brands' => (clone $publicProducts)->whereNotNull('brand')->where('brand', '!=', '')
                ->distinct()->orderBy('brand')->pluck('brand')->all(),
        ];
    }

    /**
     * Products a shopper may see: published by an active company with at least one open
     * branch. Medicines belong to the company, so closing one branch (even the first, which
     * products still point at) hides nothing; which branch has them is inStockAt()'s job.
     */
    public static function publishedProducts(): Builder
    {
        return Product::query()
            ->with('store:id,name,slug,status,city,logo_path')
            ->where('status', 'active')
            ->whereHas('tenant', fn ($query) => $query
                ->where('status', 'active')
                ->whereHas('stores', fn ($store) => $store->where('status', 'active')));
    }

    /**
     * Narrows products to those the branch has on hand and adds `branch_quantity`, the units
     * there across all of a product's variants.
     */
    public static function inStockAt(Builder $products, Store $branch): Builder
    {
        $atBranch = fn (Builder $stock): Builder => $stock->where('store_id', $branch->id)->where('quantity', '>', 0);

        return $products
            ->whereHas('branchStocks', $atBranch)
            ->withSum(['branchStocks as branch_quantity' => $atBranch], 'quantity');
    }

    /**
     * Up to three cheaper products with the same generic name, strength and dosage form,
     * cheapest first. Nothing when the product has no generic name. Products with options are
     * left out because their price depends on the option chosen.
     */
    public static function cheaperAlternatives(Builder $products, Product $product): Builder
    {
        $sameText = fn (Builder $query, string $column, ?string $value): Builder => blank($value)
            ? $query
            : $query->whereRaw("lower({$column}) = ?", [mb_strtolower(trim($value))]);

        if (blank($product->generic_name)) {
            return $products->whereRaw('1 = 0');
        }

        $products = $sameText($products, 'generic_name', $product->generic_name);
        $products = $sameText($products, 'strength', $product->strength);

        return $sameText($products, 'dosage_form', $product->dosage_form)
            ->where('products.id', '!=', $product->id)
            ->where('price', '<', (string) $product->price)
            ->whereDoesntHave('variants')
            ->reorder('price')
            ->limit(3);
    }

    /**
     * Stores a shopper may see. A suspended store or tenant disappears from the catalog.
     */
    public static function publicStores(): Builder
    {
        return Store::query()
            ->where('status', 'active')
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'));
    }
}
