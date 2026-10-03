<?php

use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::view('sign-in', 'auth.clerk', ['mode' => 'sign-in', 'afterAuthUrl' => '/'])->name('login');

    Route::get('sign-up', function () {
        $afterAuthUrl = request()->string('redirect')->toString();

        abort_unless($afterAuthUrl === '' || str_starts_with($afterAuthUrl, '/'), 422);

        return view('auth.clerk', [
            'mode' => 'sign-up',
            'afterAuthUrl' => $afterAuthUrl !== '' ? $afterAuthUrl : '/',
        ]);
    })->name('register');

    Route::redirect('seller/register', '/sign-up?redirect=/start-selling')->name('seller.register');
});

Route::post('sign-out', fn () => view('auth.sign-out'))->middleware('auth')->name('logout');
