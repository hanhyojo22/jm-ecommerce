<?php

use App\Http\Controllers\Api\V1\AccountController;
use Illuminate\Support\Facades\Route;

/*
 * Additive, versioned JSON surface for the mobile client. The Blade app keeps using
 * the web routes; both share the same Clerk guard, policies, actions, and queries.
 */
Route::prefix('v1')->group(function (): void {
    Route::get('/me', AccountController::class)
        ->middleware(['auth', 'clerk.verified', 'throttle:api'])
        ->name('api.v1.me');
});
