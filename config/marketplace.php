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
        // Days after delivery a buyer can still open a return.
        'return_window_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Online Payments (PayMongo)
    |--------------------------------------------------------------------------
    |
    | One PayMongo checkout pays every store in the order: each store's share
    | is split to its PayMongo account and JMMart keeps its platform fee. The
    | buyer pays PayMongo's fee as a separate line. PayMongo takes its fee
    | before splitting, and if the shares exceed what is left the split fails
    | silently, so these rates must be at least what the PayMongo contract
    | charges. An order still unpaid after `payment_hours` is cancelled.
    |
    */

    'online_payment' => [
        'fees' => [
            'gcash' => ['rate' => '0.025', 'fixed' => '0.00'],
            'maya' => ['rate' => '0.022', 'fixed' => '0.00'],
            'card' => ['rate' => '0.035', 'fixed' => '15.00'],
        ],
        'payment_hours' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cash on Delivery Refusals
    |--------------------------------------------------------------------------
    |
    | Many stores take only COD, so a buyer who keeps refusing parcels is not
    | blocked: after this many refusals within the window they can have only
    | one order in progress at a time, until support lifts the limit.
    |
    */

    'cod_refusals' => [
        'limit' => 2,
        'window_days' => 180,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart Reminders
    |--------------------------------------------------------------------------
    |
    | A buyer whose cart has gone unchanged this many hours is pushed one
    | reminder. Changing the cart starts a new idle stretch.
    |
    */

    'cart' => [
        'reminder_hours' => 24,
    ],

];
