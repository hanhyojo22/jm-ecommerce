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
            'afterAuthUrl' => route('clerk.handoff', ['redirect' => $redirect !== '' ? $redirect : '/']),
        ]);
    })->name('login');

    Route::get('sign-up', function () {
        $afterAuthUrl = request()->string('redirect')->toString();

        abort_unless($afterAuthUrl === '' || (str_starts_with($afterAuthUrl, '/') && ! str_starts_with($afterAuthUrl, '//') && ! str_contains($afterAuthUrl, '\\')), 422);

        return view('auth.clerk', [
            'mode' => 'sign-up',
            'afterAuthUrl' => route('clerk.handoff', ['redirect' => $afterAuthUrl !== '' ? $afterAuthUrl : '/']),
        ]);
    })->name('register');

    Route::redirect('seller/register', '/sign-up?redirect=/start-selling')->name('seller.register');
});

Route::post('sign-out', fn () => view('auth.sign-out'))->middleware('auth')->name('logout');
