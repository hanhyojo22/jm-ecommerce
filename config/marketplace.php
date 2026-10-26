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

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    |
    | A shipped store order the buyer never confirms is marked delivered after
    | this many days, so it starts its return window and is billed its fee.
    | Orders with an open help request wait for support instead.
    |
    */

    'orders' => [
        'auto_deliver_days' => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cash on Delivery Refusals
    |--------------------------------------------------------------------------
    |
    | COD is the only way to pay, so a buyer who keeps refusing parcels is not
    | blocked: after this many refusals within the window they can have only
    | one order in progress at a time, until support lifts the limit.
    |
    */

    'cod_refusals' => [
        'limit' => 2,
        'window_days' => 180,
    ],

];
