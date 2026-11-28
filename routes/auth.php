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
 * a staff or administrator invitation: Clerk sends the invitee here with a __clerk_ticket. An invitation
 * always gets this page, even in a browser still signed in as someone else: the page asks Clerk itself
 * and offers to sign that person out (resources/js/clerk-invitation.js). The server cannot do that
 * reliably, since its session cookie lapses after 60 seconds while Clerk's session does not.
 */
Route::get('sign-up', function () {
    $isInvitation = request()->filled('__clerk_ticket');
    abort_unless(config('marketplace.web_store.enabled') || $isInvitation, 404);

    if (request()->user() !== null && ! $isInvitation) {
        return redirect()->route(request()->user()->landingRouteName());
    }

    $afterAuthUrl = request()->string('redirect')->toString();

    abort_unless($afterAuthUrl === '' || (str_starts_with($afterAuthUrl, '/') && ! str_starts_with($afterAuthUrl, '//') && ! str_contains($afterAuthUrl, '\\')), 422);

    return view('auth.clerk', [
        'mode' => 'sign-up',
        'afterAuthUrl' => route('clerk.handoff', $afterAuthUrl !== '' ? ['redirect' => $afterAuthUrl] : []),
    ]);
})->name('register');

Route::post('sign-out', fn () => response()->view('auth.sign-out')->withoutCookie('__session'))->middleware('auth')->name('logout');
