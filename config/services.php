<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'clerk' => [
        'publishable_key' => env('CLERK_PUBLISHABLE_KEY'),
        'secret_key' => env('CLERK_SECRET_KEY'),
        'authorized_parties' => array_values(array_filter(array_map('trim', explode(',', env('CLERK_AUTHORIZED_PARTIES', env('APP_URL', '')))))),
        'audiences' => array_values(array_filter(array_map('trim', explode(',', env('CLERK_JWT_AUDIENCES', ''))))),
        'sign_in_url' => env('CLERK_SIGN_IN_URL', '/sign-in'),
        'sign_up_url' => env('CLERK_SIGN_UP_URL', '/sign-up'),
        'webhook_signing_secret' => env('CLERK_WEBHOOK_SIGNING_SECRET'),

        /*
         * Clerk session tokens expire 60 seconds after they are minted, so the backend
         * cookie that carries one may never outlive the token it holds. This value is a
         * browser-side cleanup hint only: authenticity is always decided by verifying the
         * token's signature, `exp` claim and `session_token` type against the Clerk secret
         * key on every request, so it cannot extend a session on its own.
         */
        'session_cookie_lifetime_minutes' => (int) env('CLERK_SESSION_COOKIE_LIFETIME', 1),
    ],

    'playwright' => [
        'auth_key' => env('PLAYWRIGHT_AUTH_KEY'),
    ],

    'gmail' => [
        'client_id' => env('GMAIL_CLIENT_ID'),
        'client_secret' => env('GMAIL_CLIENT_SECRET'),
        'refresh_token' => env('GMAIL_REFRESH_TOKEN'),
        /*
         * Must be the mailbox the refresh token was issued for, or an alias verified on it.
         * Gmail sends as the authenticated account, so a From on any other domain goes out
         * unaligned with the signature and is filtered as spoofed. No default on purpose:
         * sending nothing is better than sending as a domain this app cannot authenticate for.
         */
        'from' => env('GMAIL_FROM_ADDRESS'),
    ],

    'paymongo' => [
        'secret_key' => env('PAYMONGO_SECRET_KEY'),
        // Shown once when the webhook endpoint is created in the PayMongo dashboard.
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
        // JMMart's own merchant ID, which keeps its fee share and gives it back on refunds.
        'merchant_id' => env('PAYMONGO_MERCHANT_ID'),
        'base_url' => env('PAYMONGO_BASE_URL', 'https://api.paymongo.com'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
