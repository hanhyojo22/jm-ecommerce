<?php

namespace App\Queries;

use App\Models\Category;
use App\Models\IngredientAlias;
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
            ->where(function (Builder $query) use ($prefix, $wordMatch, $wordBindings, $useFullText, $search, $escaped): void {
                $query->whereRaw("LOWER(products.name) LIKE ? ESCAPE '!'", [$prefix])
                    ->orWhereRaw($wordMatch, $wordBindings);
                if ($useFullText && Str::length($search) >= 3) {
                    $query->orWhereRaw('LOWER(products.name) %>> ?', [$search]);
                }
                $query->orWhereRaw("LOWER(products.generic_name) LIKE ? ESCAPE '!'", ['%'.$escaped.'%'])
                    ->orWhereRaw("products.search_keywords LIKE ? ESCAPE '!'", ['%'.$escaped.'%']);
            });

        $rank = "CASE WHEN LOWER(products.name) = ? THEN 0 WHEN LOWER(products.name) LIKE ? ESCAPE '!' THEN 1 WHEN {$wordMatch} THEN 2 WHEN LOWER(products.generic_name) LIKE ? ESCAPE '!' THEN 3 ELSE 4 END";

        return $query->orderByRaw($rank, [$search, $prefix, ...$wordBindings, '%'.$escaped.'%'])
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
     *     brands: Collection<int, string>,
     *     search: array{ingredient: string|null}
     * }
     */
    public static function search(array $filters, ?Store $seller = null, int $perPage = 12, ?Store $branch = null): array
    {
        $search = trim($filters['q'] ?? '');
        $normalizedSearch = Str::lower($search);
        $escapedSearch = self::escapeLike($normalizedSearch);
        $searchPrefix = $escapedSearch.'%';
        $searchTerm = '%'.$escapedSearch.'%';
        $ingredients = $search === '' ? null : self::ingredientSearch($search, $searchPrefix);
        $genericMatch = $ingredients === null ? '1 = 0' : '('.implode(' OR ', array_fill(0, count($ingredients['terms']), "LOWER(products.generic_name) LIKE ? ESCAPE '!'")).')';
        $genericBindings = $ingredients === null ? [] : array_map(fn (string $term): string => '%'.self::escapeLike($term).'%', $ingredients['terms']);

        $categoryProducts = self::publishedProducts()
            ->when($seller, fn (Builder $query) => $query->whereBelongsTo($seller))
            // Categories follow the same branch stock as the products below.
            ->when($branch, fn (Builder $query) => $query->whereHas('branchStocks', fn (Builder $stock) => $stock->where('store_id', $branch->id)->where('quantity', '>', 0)));

        $catalogProducts = (clone $categoryProducts)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereRaw("LOWER(products.name) LIKE ? ESCAPE '!'", [$searchTerm])
                ->orWhereRaw("LOWER(products.description) LIKE ? ESCAPE '!'", [$searchTerm])
                ->orWhereRaw("LOWER(products.brand) LIKE ? ESCAPE '!'", [$searchTerm])
                // Keywords are saved lowercase, so they compare without LOWER().
                ->orWhereRaw("products.search_keywords LIKE ? ESCAPE '!'", [$searchTerm])
                ->orWhereRaw($genericMatch, $genericBindings)
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

        // Relevance search on an ingredient groups results: what was typed, other brands of it, combinations, the rest.
        $grouped = $ingredients !== null && ($filters['sort'] ?? 'relevance') === 'relevance';
        $hasIngredientSections = false;
        $hasTopMatch = false;
        if ($grouped) {
            $sameMedicine = $ingredients['brand_generic'] === null ? '1 = 0' : 'LOWER(products.generic_name) = ?';
            $groupSql = <<<SQL
                CASE
                    WHEN LOWER(products.name) = ? OR LOWER(products.name) LIKE ? ESCAPE '!' OR LOWER(products.brand) = ? THEN 0
                    WHEN {$sameMedicine} OR ({$genericMatch} AND products.generic_name NOT LIKE '%+%') THEN 1
                    WHEN {$genericMatch} THEN 2
                    ELSE 3
                END
            SQL;
            $groupBindings = [
                $normalizedSearch, $searchPrefix, $normalizedSearch,
                ...array_filter([$ingredients['brand_generic']]),
                ...$genericBindings, ...$genericBindings,
            ];
            $groupsPresent = $products->toBase()->cloneWithout(['columns', 'orders'])->cloneWithoutBindings(['select', 'order'])
                ->selectRaw("MAX(CASE WHEN ({$groupSql}) IN (1, 2) THEN 1 ELSE 0 END) AS sections, MAX(CASE WHEN ({$groupSql}) = 0 THEN 1 ELSE 0 END) AS top_match", [...$groupBindings, ...$groupBindings])
                ->first();
            $hasIngredientSections = (bool) ($groupsPresent?->sections ?? false);
            $hasTopMatch = (bool) ($groupsPresent?->top_match ?? false);
            if ($hasIngredientSections) {
                $products->selectRaw("({$groupSql}) AS search_group", $groupBindings)
                    ->orderByRaw($groupSql, $groupBindings)
                    ->orderByRaw("CASE WHEN ({$groupSql}) IN (1, 2) THEN LOWER(products.generic_name) END", $groupBindings);
                // The strength and form of the brand typed lead its group, ahead of syrups or other strengths.
                if ($ingredients['brand_strength'] !== null || $ingredients['brand_dosage_form'] !== null) {
                    $products->orderByRaw(
                        "CASE WHEN ({$groupSql}) IN (1, 2) AND COALESCE(LOWER(products.strength), '') = ? AND COALESCE(LOWER(products.dosage_form), '') = ? THEN 0 ELSE 1 END",
                        [...$groupBindings, Str::lower((string) $ingredients['brand_strength']), Str::lower((string) $ingredients['brand_dosage_form'])],
                    );
                }
                foreach (['LOWER(products.strength)', 'LOWER(products.dosage_form)', 'products.price'] as $column) {
                    $products->orderByRaw("CASE WHEN ({$groupSql}) IN (1, 2) THEN {$column} END", $groupBindings);
                }
            }
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

        $paginator = $products->paginate($perPage)->withQueryString();
        if ($hasIngredientSections) {
            $paginator->getCollection()->each(fn (Product $product) => $product->setAttribute('search_section', match ((int) $product->search_group) {
                0 => null,
                // "Other" only reads right under the brand the shopper typed.
                1 => ($hasTopMatch ? 'Other brands of ' : '').self::withoutParenthetical($product->generic_name),
                2 => 'Also contains '.self::containedIngredient($product->generic_name, $ingredients),
                default => 'Other results',
            }));
        }

        return [
            'products' => $paginator,
            'categories' => $categories,
            'brands' => $brands,
            'search' => ['ingredient' => $ingredients['alias_of'] ?? null],
        ];
    }

    /**
     * What an ingredient search should also match in `generic_name`: the generic of the brand
     * the shopper typed (so "Biogesic" also finds other paracetamol), and the ingredient and
     * every alias of either (so "vitamin c" also finds sodium ascorbate).
     *
     * @return array{terms: list<string>, brand_generic: string|null, brand_strength: string|null, brand_dosage_form: string|null, label: string, alias_of: string|null}
     */
    private static function ingredientSearch(string $search, string $searchPrefix): array
    {
        $normalizedSearch = Str::lower($search);
        $brand = Str::length($normalizedSearch) < 3 ? null : self::publishedProducts()
            ->without('store')
            ->whereNotNull('generic_name')
            ->where('generic_name', '!=', '')
            ->where(fn (Builder $query) => $query
                ->whereRaw("LOWER(products.name) LIKE ? ESCAPE '!'", [$searchPrefix])
                ->orWhereRaw('LOWER(products.brand) = ?', [$normalizedSearch]))
            ->orderByRaw('CASE WHEN LOWER(products.name) = ? OR LOWER(products.brand) = ? THEN 0 ELSE 1 END', [$normalizedSearch, $normalizedSearch])
            ->orderByRaw('LENGTH(products.name)')
            ->first(['generic_name', 'strength', 'dosage_form']);
        $brandGeneric = $brand?->generic_name;
        $generic = $brandGeneric === null ? null : self::withoutParenthetical($brandGeneric);
        $names = array_values(array_unique(array_filter([$normalizedSearch, Str::lower((string) $generic)])));

        $aliases = IngredientAlias::query()
            ->whereIn('ingredient', IngredientAlias::query()->select('ingredient')
                ->whereIn('alias', $names)
                ->orWhereRaw('LOWER(ingredient) IN ('.implode(', ', array_fill(0, count($names), '?')).')', $names))
            ->get(['alias', 'ingredient']);
        $ingredient = $aliases->first()?->ingredient;

        return [
            'terms' => collect($names)
                ->merge($aliases->pluck('alias'))
                ->push(Str::lower((string) $ingredient))
                ->filter()->unique()->values()->all(),
            'brand_generic' => $brandGeneric === null ? null : Str::lower(trim($brandGeneric)),
            'brand_strength' => $brand?->strength,
            'brand_dosage_form' => $brand?->dosage_form,
            'label' => $ingredient ?? $generic ?? Str::ucfirst($search),
            'alias_of' => $aliases->firstWhere('alias', $normalizedSearch)?->ingredient,
        ];
    }

    /**
     * The searched ingredient as this combination's own generic name spells it, so "Sodium
     * Ascorbate + Zinc" found through "vitamin c" says Sodium Ascorbate, not Ascorbic Acid.
     *
     * @param  array{terms: list<string>, label: string}  $ingredients
     */
    private static function containedIngredient(string $genericName, array $ingredients): string
    {
        foreach ($ingredients['terms'] as $term) {
            $position = mb_stripos($genericName, $term);
            if ($position !== false) {
                return mb_substr($genericName, $position, mb_strlen($term));
            }
        }

        return $ingredients['label'];
    }

    /** "Ascorbic Acid (Vitamin C)" → "Ascorbic Acid". */
    private static function withoutParenthetical(string $value): string
    {
        return trim(preg_replace('/\s*\([^)]*\)/', '', $value));
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
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
                        WHEN LOWER(products.generic_name) LIKE ? ESCAPE '!' THEN 5
                        WHEN products.search_keywords LIKE ? ESCAPE '!' THEN 6
                        WHEN LOWER(products.description) LIKE ? ESCAPE '!' THEN 7
                        ELSE 8
                    END
                SQL,
                [$search, $searchPrefix, $searchTerm, $search, $searchTerm, $searchTerm, $searchTerm, $searchTerm],
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
     * Every other product with the same generic name, strength and dosage form, cheaper and
     * dearer, by price. The law asks for all of them (RA 6675 §6(d)), so nothing is picked out.
     * Nothing when any of the three is blank, so an unfilled strength never matches every
     * strength. Spacing and case are ignored ("500mg" = "500 MG"). Products with options are
     * left out because their price depends on the option chosen.
     */
    public static function sameMedicine(Builder $products, Product $product): Builder
    {
        if (blank($product->generic_name) || blank($product->strength) || blank($product->dosage_form)) {
            return $products->whereRaw('1 = 0');
        }

        foreach (['generic_name', 'strength', 'dosage_form'] as $column) {
            $products->whereRaw("REPLACE(LOWER(products.{$column}), ' ', '') = ?", [preg_replace('/\s+/u', '', mb_strtolower($product->{$column}))]);
        }

        return $products
            ->where('products.id', '!=', $product->id)
            ->whereDoesntHave('variants')
            ->reorder('price')
            ->orderBy('products.name');
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
