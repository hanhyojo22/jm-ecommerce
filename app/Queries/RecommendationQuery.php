<?php

namespace App\Queries;

use App\Models\Order;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Support\CookiePreferences;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RecommendationQuery
{
    private const SEARCHES_KEY = 'marketplace.recent_searches';

    public static function rememberSearch(Request $request, string $search): void
    {
        $search = Str::lower(trim($search));

        if (! self::hasConsent($request) || Str::length($search) < 2 || ! preg_match('/[\pL\pN]/u', $search)) {
            return;
        }

        $previous = $request->session()->get(self::SEARCHES_KEY, []);
        $request->session()->put(self::SEARCHES_KEY, array_slice(array_values(array_unique([$search, ...$previous])), 0, 5));
    }

    public static function clearHistory(Request $request): void
    {
        $request->session()->forget(['marketplace.recently_viewed', self::SEARCHES_KEY]);
    }

    /** @return Collection<int, Product> */
    public static function forRequest(Request $request, ?string $currentSearch = null, int $limit = 4): Collection
    {
        $consented = self::hasConsent($request);
        $searches = $consented && $request->hasSession() ? $request->session()->get(self::SEARCHES_KEY, []) : [];
        $currentSearch = Str::lower(trim((string) $currentSearch));

        if (Str::length($currentSearch) >= 2 && preg_match('/[\pL\pN]/u', $currentSearch)) {
            $searches = [$currentSearch];
        }

        $searches = array_slice($searches, 0, 5);
        $viewedIds = $consented && $request->hasSession() ? $request->session()->get('marketplace.recently_viewed', []) : [];
        $viewed = $viewedIds === [] ? collect() : CatalogQuery::publishedProducts()
            ->whereKey(array_slice($viewedIds, 0, 8))->get(['products.id', 'products.category_id', 'products.brand']);

        $purchased = collect();
        if ($consented && $request->user() !== null) {
            $orders = Order::query()->where('user_id', $request->user()->id)
                ->where('created_at', '>=', now()->subDays(90))
                ->with(['payment:id,order_id,method,status', 'sellerOrders.items.product:id,category_id,brand'])
                ->latest()->limit(10)->get();

            $purchased = $orders->filter(fn (Order $order): bool => $order->payment !== null
                && ! in_array($order->payment->status, ['failed', 'refunded'], true)
                && ($order->payment->method === 'cod' || $order->payment->status === 'paid'))
                ->flatMap(fn (Order $order) => $order->sellerOrders
                    ->where('status', '!=', 'cancelled')
                    ->flatMap(fn (SellerOrder $sellerOrder) => $sellerOrder->items)
                    ->pluck('product')->filter());
        }

        $searchMatches = collect();
        foreach ($searches as $search) {
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
            $term = '%'.$escaped.'%';
            $searchMatches = $searchMatches->merge(self::candidates()
                ->where(fn (Builder $query) => $query
                    ->whereRaw("LOWER(products.name) LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("LOWER(products.brand) LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("LOWER(products.description) LIKE ? ESCAPE '!'", [$term]))
                ->latest('products.created_at')->limit(16)->get());
        }

        $categoryWeights = [];
        $brandWeights = [];
        foreach ($purchased as $product) {
            if ($product->category_id !== null) {
                $categoryWeights[$product->category_id] = ($categoryWeights[$product->category_id] ?? 0) + 30;
            }
            if (filled($product->brand)) {
                $brandWeights[Str::lower($product->brand)] = ($brandWeights[Str::lower($product->brand)] ?? 0) + 8;
            }
        }
        foreach ($viewed as $product) {
            if ($product->category_id !== null) {
                $categoryWeights[$product->category_id] = ($categoryWeights[$product->category_id] ?? 0) + 12;
            }
        }
        foreach ($searchMatches->take(8) as $product) {
            if ($product->category_id !== null) {
                $categoryWeights[$product->category_id] = ($categoryWeights[$product->category_id] ?? 0) + 10;
            }
        }

        $related = $categoryWeights === [] ? collect() : self::candidates()
            ->whereIn('products.category_id', array_keys($categoryWeights))
            ->latest('products.created_at')->limit(60)->get();
        $popular = $currentSearch === '' ? TopProductQuery::featured(8) : collect();
        $fresh = $currentSearch === '' ? self::candidates()->latest('products.created_at')->limit(12)->get() : collect();
        $candidates = $searchMatches->merge($related)->merge($popular)->merge($fresh)->unique('id');
        $excludedIds = $purchased->pluck('id')->merge($viewedIds)->all();
        $managedTenantIds = $request->user()?->tenantMemberships
            ->whereIn('role', ['owner', 'manager', 'staff'])->pluck('tenant_id')->all() ?? [];
        $popularIds = $popular->pluck('id')->all();

        $ranked = $candidates->filter(fn (Product $product): bool => ! in_array($product->id, $excludedIds, true)
                && ! in_array($product->tenant_id, $managedTenantIds, true))
            ->sortByDesc(function (Product $product) use ($searches, $categoryWeights, $brandWeights, $popularIds): int {
                $score = min(90, $categoryWeights[$product->category_id] ?? 0)
                    + min(24, $brandWeights[Str::lower((string) $product->brand)] ?? 0)
                    + (in_array($product->id, $popularIds, true) ? 5 : 0)
                    + ($product->created_at?->greaterThan(now()->subDays(14)) ? 3 : 0);
                foreach ($searches as $position => $search) {
                    if (Str::contains(Str::lower($product->name.' '.$product->brand.' '.$product->description), $search)) {
                        $score += max(7, 35 - ($position * 7));
                    }
                }

                return $score;
            })->values();

        $selected = collect();
        $storeCounts = [];
        foreach ($ranked as $product) {
            if (($storeCounts[$product->store_id] ?? 0) >= 2) {
                continue;
            }
            $selected->push($product);
            $storeCounts[$product->store_id] = ($storeCounts[$product->store_id] ?? 0) + 1;
            if ($selected->count() === $limit) {
                return self::hydrate($selected);
            }
        }

        return self::hydrate($selected->merge($ranked->whereNotIn('id', $selected->pluck('id'))->take($limit - $selected->count()))->values());
    }

    private static function hasConsent(Request $request): bool
    {
        return CookiePreferences::fromRequest($request)['functional'] ?? false;
    }

    private static function candidates(): Builder
    {
        return self::availableProducts()
            ->select(['products.id', 'products.tenant_id', 'products.store_id', 'products.category_id', 'products.name', 'products.brand', 'products.description', 'products.created_at']);
    }

    /** @param Collection<int, Product> $selected
     * @return Collection<int, Product>
     */
    private static function hydrate(Collection $selected): Collection
    {
        if ($selected->isEmpty()) {
            return collect();
        }

        $products = self::products()->whereKey($selected->pluck('id'))->get()->keyBy('id');

        return $selected->map(fn (Product $product): ?Product => $products->get($product->id))->filter()->values();
    }

    private static function products(): Builder
    {
        return self::availableProducts()
            ->select(['products.id', 'products.tenant_id', 'products.store_id', 'products.category_id', 'products.name', 'products.slug', 'products.description', 'products.price', 'products.compare_at_price', 'products.brand', 'products.condition', 'products.free_shipping', 'products.stock_quantity', 'products.status', 'products.created_at'])
            ->with(['thumbnail', 'category:id,name,slug'])
            ->withExists(['variants', 'variants as variants_using_base_price_exists' => fn (Builder $query) => $query->whereNull('price')])
            ->withMin('variants', 'price')
            ->withCount(['reviews as published_reviews_count' => fn (Builder $query) => $query->published()])
            ->withAvg(['reviews as published_reviews_avg_rating' => fn (Builder $query) => $query->published()], 'rating')
            ->withSum(['orderItems as sold_quantity' => fn (Builder $query) => $query
                ->whereHas('sellerOrder', fn (Builder $sellerOrder) => $sellerOrder->where('status', '!=', 'cancelled'))], 'quantity');
    }

    private static function availableProducts(): Builder
    {
        return CatalogQuery::publishedProducts()
            ->where('products.stock_quantity', '>', 0)
            ->where(fn (Builder $query) => $query
                ->whereDoesntHave('variants')
                ->orWhereHas('variants', fn (Builder $variants) => $variants->where('stock_quantity', '>', 0)));
    }
}
