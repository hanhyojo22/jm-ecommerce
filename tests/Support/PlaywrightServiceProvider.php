<?php

namespace Tests\Support;

use App\Auth\ClerkSessionVerifier;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class PlaywrightServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $authKey = config('services.playwright.auth_key');

        if (! is_string($authKey) || $authKey === '') {
            return;
        }

        $this->app->bind(
            ClerkSessionVerifier::class,
            fn (): PlaywrightClerkSessionVerifier => new PlaywrightClerkSessionVerifier($authKey),
        );
    }

    public function boot(): void
    {
        if (config('services.playwright.auth_key')) {
            Vite::useHotFile(storage_path('framework/playwright-vite.hot'));
        }
    }
}
