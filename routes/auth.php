<?php

use App\Http\Controllers\ClerkSessionHandoffController;
use Illuminate\Support\Facades\Route;

Route::get('auth/complete', [ClerkSessionHandoffController::class, 'show'])->name('clerk.handoff');
Route::post('auth/complete', [ClerkSessionHandoffController::class, 'complete'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('clerk.handoff.complete');

Route::middleware('guest')->group(function (): void {
    Route::get('sign-in', function () {
        $redirect = request()->string('redirect')->toString();

        abort_unless($redirect === '' || (str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//') && ! str_contains($redirect, '\\')), 422);

        return view('auth.clerk', [
            'mode' => 'sign-in',
            'afterAuthUrl' => route('clerk.handoff', ['redirect' => $redirect !== '' ? $redirect : '/']),
        ]);
    })->name('login');

    Route::get('sign-up', function () {
        $afterAuthUrl = request()->string('redirect')->toString();

        abort_unless($afterAuthUrl === '' || str_starts_with($afterAuthUrl, '/'), 422);

        return view('auth.clerk', [
            'mode' => 'sign-up',
            'afterAuthUrl' => route('clerk.handoff', ['redirect' => $afterAuthUrl !== '' ? $afterAuthUrl : '/']),
        ]);
    })->name('register');

    Route::redirect('seller/register', '/sign-up?redirect=/start-selling')->name('seller.register');
});

Route::post('sign-out', fn () => view('auth.sign-out'))->middleware('auth')->name('logout');
