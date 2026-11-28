<?php

use App\Http\Controllers\ClerkSessionHandoffController;
use App\Models\Product;
use Illuminate\Support\Facades\Route;

Route::get('auth/complete', [ClerkSessionHandoffController::class, 'show'])->name('clerk.handoff');
Route::post('auth/complete', [ClerkSessionHandoffController::class, 'complete'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('clerk.handoff.complete');
Route::get('auth/refresh', [ClerkSessionHandoffController::class, 'refresh'])->name('clerk.refresh');
Route::post('auth/session', [ClerkSessionHandoffController::class, 'refreshSession'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('clerk.session.refresh');

Route::middleware('guest')->group(function (): void {
    Route::get('sign-in', function () {
        $redirect = request()->string('redirect')->toString();
        $productId = request()->query('add_to_cart');

        abort_unless($redirect === '' || (str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//') && ! str_contains($redirect, '\\')), 422);

        if (is_string($productId) && $productId !== '' && Product::query()->whereKey($productId)->exists()) {
            request()->session()->put('pending_cart_addition', [
                'product_id' => $productId,
                'expires_at' => now()->addMinutes(15)->timestamp,
            ]);
        }

        return view('auth.clerk', [
            'mode' => 'sign-in',
            'afterAuthUrl' => route('clerk.handoff', $redirect !== '' ? ['redirect' => $redirect] : []),
        ]);
    })->name('login');

});

/*
 * Customers sign up in the app, so the website's sign-up page is closed with the web store, except for
 * a staff or administrator invitation: Clerk sends the invitee here with a __clerk_ticket. A browser
 * still signed in as someone else (often the owner who sent it) is asked to sign out first, or the
 * invitation would open that person's workspace instead.
 */
Route::get('sign-up', function () {
    $isInvitation = request()->filled('__clerk_ticket');
    abort_unless(config('marketplace.web_store.enabled') || $isInvitation, 404);

    if (request()->user() !== null) {
        return $isInvitation
            ? response()->view('auth.invitation-signed-in', ['email' => request()->user()->email, 'invitationUrl' => request()->getRequestUri()])
            : redirect()->route(request()->user()->landingRouteName());
    }

    $afterAuthUrl = request()->string('redirect')->toString();

    abort_unless($afterAuthUrl === '' || (str_starts_with($afterAuthUrl, '/') && ! str_starts_with($afterAuthUrl, '//') && ! str_contains($afterAuthUrl, '\\')), 422);

    return view('auth.clerk', [
        'mode' => 'sign-up',
        'afterAuthUrl' => route('clerk.handoff', $afterAuthUrl !== '' ? ['redirect' => $afterAuthUrl] : []),
    ]);
})->name('register');

Route::post('sign-out', function () {
    // Only a same-site path, so a sign-out can return to an invitation link but never leave the site.
    $redirect = request()->string('redirect')->toString();
    $redirect = str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//') && ! str_contains($redirect, '\\') ? $redirect : '/';

    return response()->view('auth.sign-out', ['redirect' => $redirect])->withoutCookie('__session');
})->middleware('auth')->name('logout');
