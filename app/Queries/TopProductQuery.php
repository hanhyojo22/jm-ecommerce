<?php

namespace App\Queries;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TopProductQuery
{
    public const RANKING_PERIOD_DAYS = 30;

    /**
     * Aggregated sales metrics refresh every five minutes. Product availability, price,
     * visibility, store status, images, and review metrics are intentionally queried live.
     */
    private const CACHE_KEY = 'marketplace.top-products.ranking.v2';

    private const CACHE_MINUTES = 5;

    /** @return Collection<int, Product> */
    public static function featured(int $limit): Collection
    {
        $rankings = self::rankings();

        if ($rankings->isEmpty()) {
            return collect();
        }

        $products = self::products($rankings)->limit($limit)->get();
        self::attachRankingMetrics($products, $rankings);

        return $products;
    }

    public static function paginate(?string $categorySlug, int $perPage = 12): LengthAwarePaginator
    {
        $rankings = self::rankings();

        if ($rankings->isEmpty()) {
            return Product::query()->whereRaw('1 = 0')->paginate($perPage)->withQueryString();
        }

        $products = self::products($rankings, $categorySlug)->paginate($perPage)->withQueryString();
        self::attachRankingMetrics($products->getCollection(), $rankings);

        return $products;
    }

    public static function forgetCachedRankings(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function orderByPopularity(Builder $products): Builder
    {
        $productIds = self::rankings()->pluck('product_id')->values();

        if ($productIds->isNotEmpty()) {
            $orderCases = $productIds->map(fn (string $productId, int $position): string => "WHEN ? THEN {$position}")->implode(' ');
            $products->orderByRaw("CASE products.id {$orderCases} ELSE {$productIds->count()} END", $productIds->all());
        }

        return $products->latest('products.created_at')->orderBy('products.id');
    }

    /** @return Collection<int, array{product_id: string, net_quantity_sold: int, qualifying_order_count: int}> */
    private static function rankings(): Collection
    {
        /** @var array<int, array{product_id: string, net_quantity_sold: int, qualifying_order_count: int}> $rankings */
        $rankings = Cache::remember(self::CACHE_KEY, now()->addMinutes(self::CACHE_MINUTES), function (): array {
            return DB::table('order_items')
                ->join('seller_orders', 'seller_orders.id', '=', 'order_items.seller_order_id')
                ->join('orders', 'orders.id', '=', 'seller_orders.order_id')
                ->leftJoin('payments', 'payments.order_id', '=', 'orders.id')
                ->whereNotNull('order_items.product_id')
                ->where('seller_orders.status', 'delivered')
                ->where('seller_orders.updated_at', '>=', now()->subDays(self::RANKING_PERIOD_DAYS))
                ->where(function ($query): void {
                    $query->whereNull('payments.id')->orWhereNotIn('payments.status', ['failed', 'refunded']);
                })
                ->select('order_items.product_id')
                ->selectRaw('SUM(order_items.quantity) as net_quantity_sold')
                ->selectRaw('COUNT(DISTINCT seller_orders.id) as qualifying_order_count')
                ->groupBy('order_items.product_id')
                ->orderByDesc('net_quantity_sold')
                ->orderByDesc('qualifying_order_count')
                ->orderBy('order_items.product_id')
                ->get()
                ->map(fn (object $ranking): array => [
                    'product_id' => $ranking->product_id,
                    'net_quantity_sold' => (int) $ranking->net_quantity_sold,
                    'qualifying_order_count' => (int) $ranking->qualifying_order_count,
                ])
                ->all();
        });

        return collect($rankings);
    }

    /** @param Collection<int, array{product_id: string, net_quantity_sold: int, qualifying_order_count: int}> $rankings */
    private static function products(Collection $rankings, ?string $categorySlug = null): Builder
    {
        $productIds = $rankings->pluck('product_id')->values();
        $orderCases = $productIds->map(fn (string $productId, int $position): string => "WHEN ? THEN {$position}")->implode(' ');

        return Product::query()
            ->select(['id', 'tenant_id', 'store_id', 'category_id', 'name', 'slug', 'price', 'compare_at_price', 'brand', 'condition', 'free_shipping', 'stock_quantity', 'status'])
            ->with(['thumbnail', 'store:id,name,slug,status,city,logo_path'])
            ->withExists(['variants', 'variants as variants_using_base_price_exists' => fn (Builder $query) => $query->whereNull('price')])
            ->withMin('variants', 'price')
            ->withCount(['reviews as published_reviews_count' => fn (Builder $query) => $query->published()])
            ->withAvg(['reviews as published_reviews_avg_rating' => fn (Builder $query) => $query->published()], 'rating')
            ->whereIn('products.id', $productIds)
            ->where('products.status', 'active')
            ->where('products.stock_quantity', '>', 0)
            ->whereHas('store', fn (Builder $query) => $query->where('status', 'active'))
            ->whereHas('tenant', fn (Builder $query) => $query->where('status', 'active'))
            ->when($categorySlug, fn (Builder $query, string $slug) => $query->whereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('slug', $slug)))
            ->orderByRaw("CASE products.id {$orderCases} ELSE ? END", [...$productIds->all(), $productIds->count()]);
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, array{product_id: string, net_quantity_sold: int, qualifying_order_count: int}>  $rankings
     */
    private static function attachRankingMetrics(Collection $products, Collection $rankings): void
    {
        $rankingsByProductId = $rankings->keyBy('product_id');

        $products->each(function (Product $product) use ($rankingsByProductId): void {
            $ranking = $rankingsByProductId->get($product->id);
            $product->setAttribute('net_quantity_sold', $ranking['net_quantity_sold']);
            $product->setAttribute('qualifying_order_count', $ranking['qualifying_order_count']);
        });
    }
}
