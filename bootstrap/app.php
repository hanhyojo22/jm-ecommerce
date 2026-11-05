<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureAccountType;
use App\Http\Middleware\EnsureClerkEmailIsVerified;
use App\Http\Middleware\EnsurePlatformAdministrator;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ApiError;
use App\Support\SafeExceptionReporter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['__session']);
        $middleware->validateCsrfTokens(except: ['clerk/webhook', 'paymongo/webhook']);
        $middleware->append(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->api(append: [ForceJsonResponse::class]);

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
            abort_if($request->attributes->get('auth_service_unavailable') === true, 503);

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
            'platform-admin' => EnsurePlatformAdministrator::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReportDuplicates();
        $exceptions->dontFlash(['_token', 'token', 'session_token', '__session', 'authorization', 'secret', 'secret_key']);
        $exceptions->report(function (Throwable $exception): bool {
            app(SafeExceptionReporter::class)->report($exception, app()->bound('request') ? request() : null);

            return false;
        });

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            $status = $response->getStatusCode();
            $response->headers->set('X-Request-Id', (string) $request->attributes->get('request_id'));

            if ($status >= 400) {
                $response->headers->set('Cache-Control', 'no-store, private');
            }

            if (! $request->is('api/*') && ! $request->expectsJson() && ! $request->hasHeader('X-Livewire')
                && in_array($status, [404, 405, 419, 429, 500, 502, 503, 504], true)) {
                return response()->view('errors.request', ['status' => $status], $status, $response->headers->all());
            }

            return $response;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson() || $request->hasHeader('X-Livewire'),
        );

        /*
         * The mobile client reads a stable error code, never a human message. These
         * callbacks only touch /api/*; the web keeps its redirects and error pages.
         */
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->attributes->get('auth_service_unavailable') === true) {
                return $request->is('api/*')
                    ? ApiError::response('auth.unavailable', 'Sign-in is temporarily unavailable. Please try again later.', 503)
                    : ($request->expectsJson() || $request->hasHeader('X-Livewire')
                        ? response()->json(['message' => 'Sign-in is temporarily unavailable. Please try again later.'], 503)
                        : response()->view('errors.request', ['status' => 503], 503));
            }

            if (! $request->is('api/*')) {
                return $request->expectsJson() || $request->hasHeader('X-Livewire')
                    ? response()->json(['message' => 'Sign in to continue.'], 401)
                    : null;
            }

            return ApiError::response('auth.unauthenticated', 'Sign in to continue.', 401);
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::response('validation.failed', $exception->getMessage(), 422, $exception->errors());
        });

        $exceptions->render(function (HttpException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson() && ! $request->hasHeader('X-Livewire')) {
                return null;
            }

            $status = $exception->getStatusCode();
            $response = match ($status) {
                401 => ApiError::response('auth.unauthenticated', 'Sign in to continue.', 401),
                403 => ApiError::response('account.forbidden', 'You cannot perform this action.', 403),
                404 => ApiError::response('resource.not_found', 'The requested resource was not found.', 404),
                405 => ApiError::response('request.method_not_allowed', 'This request is not supported.', 405),
                409 => ApiError::response('request.conflict', 'This action is already in progress. Check its status before trying again.', 409),
                419 => ApiError::response('auth.session_expired', 'Your session expired. Refresh the page and sign in again.', 419),
                429 => ApiError::response('rate.limited', 'Too many requests. Try again shortly.', 429),
                default => ApiError::response($status >= 500 ? 'server.unavailable' : 'request.failed',
                    $status >= 500 ? 'This service is temporarily unavailable. Please try again later.' : 'This request could not be completed.', $status),
            };

            if (! $request->is('api/*')) {
                $response = response()->json([
                    'message' => $response->getData(true)['error']['message'],
                    'request_id' => $request->attributes->get('request_id'),
                ], $status);
            }

            $response->headers->add($exception->getHeaders());

            return $response;
        });

        /*
         * Last resort for the API: an unexpected failure still returns the same
         * envelope and the request id, never a framework debug page.
         */
        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($exception instanceof HttpException || $exception instanceof HttpResponseException
                || $exception instanceof ValidationException || $exception instanceof AuthenticationException) {
                return null;
            }

            $unavailable = $exception instanceof ConnectionException || $exception instanceof RequestException;
            $status = $unavailable ? 503 : 500;
            $message = $unavailable ? 'This service is temporarily unavailable. Please try again later.' : 'Something went wrong. Please try again.';

            if ($request->is('api/*')) {
                return ApiError::response($unavailable ? 'service.unavailable' : 'server.error', $message, $status);
            }

            if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
                return response()->json(['message' => $message,
                    'request_id' => $request->attributes->get('request_id')], $status);
            }

            return response()->view('errors.request', ['status' => $status], $status);
        });
    })->create();
