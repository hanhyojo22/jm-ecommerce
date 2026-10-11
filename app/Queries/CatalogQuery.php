<?php

namespace App\Queries;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogQuery
{
    /**
     * @param  array{
     *     q?: string|null,
     *     category?: string|null,
     *     seller?: string|null,
     *     sort?: 'relevance'|'newest'|'price_asc'|'price_desc'|'top_rated'|null,
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
    public static function search(array $filters, ?Store $seller = null): array
    {
        $search = trim($filters['q'] ?? '');
        $searchTerm = '%'.Str::lower($search).'%';

        $categoryProducts = self::publishedProducts()
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
            ->when(! empty($filters['in_stock']), fn ($query) => $query->where('stock_quantity', '>', 0))
            ->when($filters['brands'] ?? [], fn (Builder $query, array $brands) => $query->whereIn('brand', $brands))
            ->when($filters['conditions'] ?? [], fn (Builder $query, array $conditions) => $query->whereIn('condition', $conditions))
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

        return [
            'products' => $products->paginate(12)->withQueryString(),
            'categories' => $categories,
            'brands' => (clone $categoryProducts)->whereNotNull('brand')->where('brand', '!=', '')->distinct()->orderBy('brand')->pluck('brand'),
        ];
    }

    private static function publishedProducts(): Builder
    {
        return Product::query()
            ->with('store:id,name,slug,status')
            ->where('status', 'active')
            ->whereHas('store', fn ($query) => $query->where('status', 'active'))
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'));
    }
}
