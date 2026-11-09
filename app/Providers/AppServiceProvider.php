<?php

namespace App\Providers;

use App\Auth\ClerkBackendSessionVerifier;
use App\Auth\ClerkSessionVerifier;
use App\Auth\ClerkUserResolver;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Queries\CatalogQuery;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ClerkSessionVerifier::class, ClerkBackendSessionVerifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceHttps();
        }

        if ($trustedProxies = config('app.trusted_proxies')) {
            TrustProxies::at($trustedProxies);
        }

        Auth::viaRequest('clerk', fn ($request) => app(ClerkUserResolver::class)->resolve($request));

        // One responsive pager everywhere: marketplace cards and workspace tables share it.
        Paginator::defaultView('components.marketplace.pagination');
        Paginator::defaultSimpleView('components.marketplace.pagination');

        RateLimiter::for('cart', fn (Request $request): Limit => Limit::perMinute(30)->by(
            ($request->user()?->id ?? 'guest').'|'.$request->ip(),
        ));

        /*
         * Ticking items is idempotent and the chattiest thing the cart does — one request per
         * tick once the 350ms debounce stops collapsing them. It gets its own budget so a buyer
         * working through a long cart cannot spend the allowance that adding and removing needs.
         */
        RateLimiter::for('cart-selection', fn (Request $request): Limit => Limit::perMinute(60)->by(
            ($request->user()?->id ?? 'guest').'|'.$request->ip(),
        ));

        RateLimiter::for('checkout', fn (Request $request): Limit => Limit::perHour(10)->by(
            ($request->user()?->id ?? 'guest').'|'.$request->ip(),
        ));

        // The mobile client polls a handful of endpoints; 60 requests a minute leaves room
        // for normal browsing without letting a leaked token hammer the API.
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)->by(
            ($request->user()?->id ?? 'guest').'|'.$request->ip(),
        ));

        // The app marks a thread read on every refresh; its own budget keeps a busy chat
        // from spending the shared api allowance that browsing and message loads need.
        RateLimiter::for('conversation-read', fn (Request $request): Limit => Limit::perMinute(60)->by(
            ($request->user()?->id ?? 'guest').'|'.$request->ip(),
        ));

        RateLimiter::for('admin-mutations', fn (Request $request): Limit => Limit::perMinute(20)->by(
            ($request->user()?->id ?? 'guest').'|'.$request->ip(),
        ));

        Model::preventLazyLoading(! $this->app->isProduction());

        $forgetPublicFacets = static function (): void {
            DB::afterCommit(static function (): void {
                CatalogQuery::forgetCachedPublicFacets();
            });
        };

        Product::saved(static function (Product $product) use ($forgetPublicFacets): void {
            if ($product->wasRecentlyCreated || $product->wasChanged(['tenant_id', 'store_id', 'category_id', 'brand', 'status', 'deleted_at'])) {
                $forgetPublicFacets();
            }
        });
        Product::deleted($forgetPublicFacets);
        Store::saved(static function (Store $store) use ($forgetPublicFacets): void {
            if ($store->wasRecentlyCreated || $store->wasChanged(['tenant_id', 'status', 'deleted_at'])) {
                $forgetPublicFacets();
            }
        });
        Store::deleted($forgetPublicFacets);
        Tenant::saved(static function (Tenant $tenant) use ($forgetPublicFacets): void {
            if ($tenant->wasRecentlyCreated || $tenant->wasChanged(['status', 'deleted_at'])) {
                $forgetPublicFacets();
            }
        });
        Tenant::deleted($forgetPublicFacets);
        Category::saved($forgetPublicFacets);
        Category::deleted($forgetPublicFacets);

        View::composer('components.marketplace.header', function (ViewInstance $view): void {
            $user = request()->user();

            $publicCategoryIds = Product::query()
                ->where('status', 'active')
                ->whereHas('store', fn ($query) => $query->where('status', 'active'))
                ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
                ->whereNotNull('category_id')
                ->select('category_id');

            $sellerTenant = $user?->account_type === 'seller' ? $user->sellerTenant() : null;
            $sellerApplication = $user?->account_type === 'seller'
                ? $user->sellerApplications()->latest()->first()
                : null;

            $view->with([
                'cartItemCount' => $user?->cartItemCount() ?? 0,
                'headerCategories' => Category::query()
                    ->where('is_active', true)
                    ->whereIn('id', $publicCategoryIds)
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug']),
                'sellerTenant' => $sellerTenant,
                'sellerApplication' => $sellerApplication,
            ]);
        });

        View::composer('components.marketplace.footer', function (ViewInstance $view): void {
            $user = request()->user();
            $canUseMessages = request()->routeIs('home')
                && $user && in_array($user->account_type, ['buyer', 'seller'], true);

            $view->with([
                'mobileNavCanUseMessages' => $canUseMessages,
                'mobileNavUnreadMessageCount' => $canUseMessages && $user->can('viewAny', Conversation::class)
                    ? Conversation::query()->visibleTo($user)->where('buyer_id', $user->id)->unreadFor($user)->count()
                    : 0,
                'mobileNavMessagesUrl' => $canUseMessages
                    ? ($user->account_type === 'seller' ? route('messages.index', ['context' => 'buying']) : route('messages.index'))
                    : null,
            ]);
        });
    }
}
