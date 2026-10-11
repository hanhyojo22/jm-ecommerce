<?php

namespace App\Http\Controllers;

use App\Actions\AddProductToCart;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Cookie;

class ClerkSessionHandoffController extends Controller
{
    /**
     * Tracks how often a stale session has been re-bridged for a given destination so a
     * browser that cannot complete the handoff settles on the sign-in page instead of
     * bouncing between the destination and the bridge indefinitely.
     */
    private const REFRESH_ATTEMPTS_SESSION_KEY = 'clerk_refresh_attempts';

    private const MAX_REFRESH_ATTEMPTS = 1;

    private const MAX_TRACKED_DESTINATIONS = 10;

    /**
     * How long a bridge attempt stays on the record. A bridge the browser never finished must
     * not arm a trap for the next minute of the same session, so an attempt only counts
     * while it is recent.
     */
    private const REFRESH_ATTEMPT_COOLDOWN_SECONDS = 120;

    public function show(Request $request): View
    {
        $afterAuthUrl = $request->has('redirect') ? $this->safeRedirectPath($request->string('redirect')->toString()) : '';

        return view('auth.handoff', [
            'afterAuthUrl' => $afterAuthUrl,
            'signInUrl' => route('login', $afterAuthUrl !== '' ? ['redirect' => $afterAuthUrl] : []),
        ]);
    }

    /**
     * Re-establish the backend Clerk session for a destination that was requested after the
     * previous 60 second session token expired, then return the visitor to it.
     *
     * Clerk session tokens are only valid for 60 seconds, so a page loaded more than a minute
     * after the last handoff carries no verifiable credential. Browsing to a protected route
     * in that window is not a sign-out, so it is answered with this same-origin bridge instead
     * of the sign-in page. Nothing is granted here: the bridge still has to hand off a live
     * token that the server verifies through the usual authenticated endpoint.
     */
    public function refresh(Request $request): View|RedirectResponse
    {
        $redirect = $this->safeRedirectPath($request->string('redirect')->toString());

        if ($request->user() !== null) {
            return redirect()->to($redirect);
        }

        if ($this->hasExhaustedRefreshAttempts($request, $redirect)) {
            return to_route('login', ['redirect' => $redirect]);
        }

        return view('auth.handoff', [
            'afterAuthUrl' => $redirect,
            'signInUrl' => route('login', ['redirect' => $redirect]),
        ]);
    }

    /**
     * Hand a signed-in visitor a fresh backend session cookie without finishing a sign-in.
     *
     * Navigation calls this before opening a protected page, because the cookie minted by the
     * previous handoff may already have lapsed. It deliberately does nothing else: resolving a
     * post-sign-in destination and replaying a deferred cart addition belong to `complete()`,
     * and they must never fire as a side effect of simply renewing a session.
     */
    public function refreshSession(Request $request): JsonResponse
    {
        $request->session()->forget(self::REFRESH_ATTEMPTS_SESSION_KEY);

        $sessionToken = $request->bearerToken();

        if (! is_string($sessionToken) || $sessionToken === '') {
            return response()->json(['message' => 'A Clerk session token is required.'], 422);
        }

        return response()->json(['refreshed' => true])
            ->withCookie($this->sessionCookie($request, $sessionToken));
    }

    public function complete(Request $request, AddProductToCart $addProductToCart): JsonResponse
    {
        $requestedRedirect = $request->string('redirect')->toString();
        $redirect = $requestedRedirect === ''
            ? route($request->user()->landingRouteName(), [], false)
            : $this->safeRedirectPath($requestedRedirect);

        $request->session()->forget(self::REFRESH_ATTEMPTS_SESSION_KEY);

        $pendingCartAddition = $request->session()->pull('pending_cart_addition');
        if (
            is_array($pendingCartAddition)
            && ($pendingCartAddition['expires_at'] ?? 0) >= now()->timestamp
            && is_string($pendingCartAddition['product_id'] ?? null)
            && $pendingCartAddition['product_id'] !== ''
            && in_array($request->user()->account_type, ['buyer', 'seller'], true)
            && $request->user()->is_active
            && $request->user()->hasVerifiedClerkEmail()
        ) {
            $product = Product::query()->find($pendingCartAddition['product_id']);

            if ($product !== null) {
                try {
                    $addProductToCart->handle($request->user(), $product, 1);
                    $request->session()->flash('status', 'Added to your cart.');
                    $redirect = '/cart';
                } catch (ValidationException) {
                    // The product may have sold out while the buyer was signing in.
                }
            }
        }

        $response = response()->json(['redirect' => $redirect]);

        $sessionToken = $request->bearerToken();

        if (is_string($sessionToken) && $sessionToken !== '') {
            $response->withCookie($this->sessionCookie($request, $sessionToken));
        }

        return $response;
    }

    /**
     * The backend-readable copy of a Clerk session token, which must never outlive the token
     * it carries. Clerk reads it back out of the browser, so it is not HttpOnly.
     */
    private function sessionCookie(Request $request, string $sessionToken): Cookie
    {
        return cookie(
            '__session',
            $sessionToken,
            max(1, (int) config('services.clerk.session_cookie_lifetime_minutes', 1)),
            '/',
            null,
            $request->isSecure(),
            false,
            false,
            'lax',
        );
    }

    private function hasExhaustedRefreshAttempts(Request $request, string $redirect): bool
    {
        $attempts = $request->session()->get(self::REFRESH_ATTEMPTS_SESSION_KEY, []);
        $attempts = is_array($attempts) ? $attempts : [];

        if (count($attempts) > self::MAX_TRACKED_DESTINATIONS) {
            $attempts = array_slice($attempts, -self::MAX_TRACKED_DESTINATIONS, null, true);
        }

        $recorded = is_array($attempts[$redirect] ?? null) ? $attempts[$redirect] : [];
        $recordedAt = (int) ($recorded['at'] ?? 0);
        $count = (int) ($recorded['count'] ?? 0);

        if ($recordedAt > 0 && $recordedAt + self::REFRESH_ATTEMPT_COOLDOWN_SECONDS < now()->timestamp) {
            $count = 0;
        }

        $count += 1;
        $attempts[$redirect] = ['count' => $count, 'at' => now()->timestamp];
        $request->session()->put(self::REFRESH_ATTEMPTS_SESSION_KEY, $attempts);

        return $count > self::MAX_REFRESH_ATTEMPTS;
    }

    private function safeRedirectPath(string $path): string
    {
        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\') || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return '/';
        }

        return $path;
    }
}
