<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | The access token for your own Square account: a personal access token
    | from the Developer Console, or a seller's OAuth access token. Sandbox
    | and production have different tokens and application IDs.
    |
    | @see https://developer.squareup.com/docs/build-basics/access-tokens
    |
    */

    'access_token' => env('SQUARE_ACCESS_TOKEN'),

    // "sandbox" or "production".
    'environment' => env('SQUARE_ENVIRONMENT', 'sandbox'),

    // The Square-Version header (e.g. "2026-09-16"). Leave empty to use the
    // version the installed square/square SDK was generated for.
    'version' => env('SQUARE_VERSION'),

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | The location used by Square::locationId() and the card form, and the
    | currency used by Square::money() when you don't pass one.
    |
    */

    'location_id' => env('SQUARE_LOCATION_ID'),

    'currency' => env('SQUARE_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Application
    |--------------------------------------------------------------------------
    |
    | The application ID is public: the Web Payments SDK card form needs it.
    | The application secret is only needed for OAuth (multi-merchant apps).
    |
    */

    'application_id' => env('SQUARE_APPLICATION_ID'),

    'application_secret' => env('SQUARE_APPLICATION_SECRET'),

    'oauth' => [
        'redirect_uri' => env('SQUARE_OAUTH_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | Square signs each notification with HMAC-SHA256 over the notification
    | URL followed by the raw body. The URL must match the one in your webhook
    | subscription exactly, so set it here rather than relying on the URL the
    | request arrived at (proxies and load balancers can change it).
    |
    | Set "path" (e.g. "square/webhook") to register the package's webhook
    | route, which verifies the signature and dispatches Laravel events.
    |
    | @see https://developer.squareup.com/docs/webhooks/step3validate
    |
    */

    'webhooks' => [

        'signature_key' => env('SQUARE_WEBHOOK_SIGNATURE_KEY'),

        'url' => env('SQUARE_WEBHOOK_URL'),

        'path' => env('SQUARE_WEBHOOK_PATH'),

        // Square may send an event more than once. When enabled, each event_id
        // is remembered for 'deduplicate_hours' and repeats are acknowledged
        // without dispatching events again.
        'deduplicate' => true,

        'deduplicate_hours' => 48,

        'cache_store' => env('SQUARE_CACHE_STORE'),

    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    |
    | The SDK sends its requests through Laravel's HTTP client. The SDK retries
    | connection errors, 408, 429 and 5xx responses itself, with backoff.
    |
    */

    'timeout' => (int) env('SQUARE_TIMEOUT', 30),

    'retries' => 2,

];
