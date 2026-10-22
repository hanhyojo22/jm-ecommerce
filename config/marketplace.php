<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Fee
    |--------------------------------------------------------------------------
    |
    | Stores collect cash on delivery, so JMMart bills its share monthly
    | instead of deducting it. The rate is a string for bcmath, and each
    | earning keeps the rate it was charged at.
    |
    */

    'platform_fee' => [
        'rate' => '0.01',
        'policy_version' => '2.0',
        'due_days' => 15,
        'payment_instructions' => env('PLATFORM_FEE_PAYMENT_INSTRUCTIONS'),
    ],

];
