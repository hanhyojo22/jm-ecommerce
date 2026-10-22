<?php

use App\Http\Middleware\EnsureAccountType;
use App\Http\Middleware\EnsureClerkEmailIsVerified;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['__session']);
        $middleware->validateCsrfTokens(except: ['clerk/webhook']);

        /*
         * Clerk session tokens expire 60 seconds after they are minted, so the `__session`
         * cookie that backs server-side authentication stops verifying as soon as that
         * window lapses. Safe navigation is therefore answered with the same-origin session
         * bridge, which re-runs the existing token handoff and returns the visitor to the
         * page they asked for. Unsafe verbs keep going straight to sign-in so a
         * state-changing request is never replayed, and the bridge grants nothing on its
         * own: the token it hands off is verified exactly as before. When the browser sent
         * a same-origin referer, sign-in returns to the page that submitted the form.
         */
        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->isMethodSafe() && ! $request->expectsJson()) {
                return route('clerk.refresh', ['redirect' => $request->getRequestUri()]);
            }

            $referer = $request->headers->get('referer');
            $refererHost = is_string($referer) ? parse_url($referer, PHP_URL_HOST) : null;
            $path = is_string($refererHost) && strcasecmp($refererHost, $request->getHost()) === 0
                ? parse_url($referer, PHP_URL_PATH)
                : null;
            $redirect = is_string($path)
                && str_starts_with($path, '/')
                && ! str_starts_with($path, '//')
                && ! str_contains($path, '\\')
                && ! preg_match('/[\x00-\x1F\x7F]/', $path)
                    ? $path
                    : null;

            return route('login', $redirect !== null ? ['redirect' => $redirect] : []);
        });

        $middleware->redirectUsersTo(fn (Request $request): string => route($request->user()->landingRouteName()));

        $middleware->alias([
            'account' => EnsureAccountType::class,
            'clerk.verified' => EnsureClerkEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
