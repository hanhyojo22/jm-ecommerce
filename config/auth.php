<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Clerk verifies every authenticated request. Local users supply
    | marketplace authorization and ownership only.
    |
    */

    'defaults' => [
        'guard' => 'clerk',
    ],

    'guards' => [
        'clerk' => ['driver' => 'clerk'],
    ],
];
