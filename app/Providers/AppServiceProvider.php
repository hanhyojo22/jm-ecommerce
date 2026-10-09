<?php

namespace App\Providers;

use App\Auth\ClerkBackendSessionVerifier;
use App\Auth\ClerkSessionVerifier;
use App\Auth\ClerkUserResolver;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Product;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;
use Tests\Support\PlaywrightClerkSessionVerifier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $playwrightAuthKey = config('services.playwright.auth_key');

        if ($this->app->environment('testing') && is_string($playwrightAuthKey) && $playwrightAuthKey !== '') {
            $this->app->bind(
                ClerkSessionVerifier::class,
                fn (): PlaywrightClerkSessionVerifier => new PlaywrightClerkSessionVerifier($playwrightAuthKey),
            );

            return;
        }

        $this->app->bind(ClerkSessionVerifier::class, ClerkBackendSessionVerifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::viaRequest('clerk', fn ($request) => app(ClerkUserResolver::class)->resolve($request));

        RateLimiter::for('cart', fn (Request $request): Limit => Limit::perMinute(30)->by(
            ($request->user()?->id ?? 'guest').'|'.$request->ip(),
        ));

        RateLimiter::for('checkout', fn (Request $request): Limit => Limit::perHour(10)->by(
            ($request->user()?->id ?? 'guest').'|'.$request->ip(),
        ));

        RateLimiter::for('admin-mutations', fn (Request $request): Limit => Limit::perMinute(20)->by(
            ($request->user()?->id ?? 'guest').'|'.$request->ip(),
        ));

        Model::preventLazyLoading(! $this->app->isProduction());

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
                'unreadConversationCount' => $user?->can('viewAny', Conversation::class)
                    ? Conversation::query()->visibleTo($user)->unreadFor($user)->count()
                    : 0,
                'headerCategories' => Category::query()
                    ->where('is_active', true)
                    ->whereIn('id', $publicCategoryIds)
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug']),
                'sellerTenant' => $sellerTenant,
                'sellerApplication' => $sellerApplication,
            ]);
        });
    }
}
