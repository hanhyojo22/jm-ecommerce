<?php

use App\Providers\AppServiceProvider;
use Tests\Support\PlaywrightServiceProvider;

$providers = [
    AppServiceProvider::class,
];

if (env('APP_ENV') === 'testing' && is_string(env('PLAYWRIGHT_AUTH_KEY')) && env('PLAYWRIGHT_AUTH_KEY') !== '') {
    $providers[] = PlaywrightServiceProvider::class;
}

return $providers;
