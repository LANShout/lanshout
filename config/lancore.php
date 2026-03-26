<?php

return [

    /*
    |--------------------------------------------------------------------------
    | LanCore Integration Toggle
    |--------------------------------------------------------------------------
    |
    | Enables or disables the LanCore identity integration entirely.
    | When disabled, all LanCore-related endpoints will return a 503
    | and the LanCore client will short-circuit.
    |
    */

    'enabled' => env('LANCORE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | LanCore Base URL
    |--------------------------------------------------------------------------
    |
    | The root URL of the LanCore instance this LanShout deployment
    | communicates with, without a trailing slash.
    |
    */

    'base_url' => env('LANCORE_BASE_URL', 'http://localhost:8080'),

    /*
    |--------------------------------------------------------------------------
    | LanCore Internal URL
    |--------------------------------------------------------------------------
    |
    | Used for server-to-server API calls from within Docker.
    | When running in containers, 'localhost' in LANCORE_BASE_URL
    | refers to the browser's host, not the container network.
    | Set this to a hostname reachable from LanShout's container
    | (e.g. host.docker.internal). Falls back to base_url if unset.
    |
    */

    'internal_url' => env('LANCORE_INTERNAL_URL'),

    /*
    |--------------------------------------------------------------------------
    | Integration Token
    |--------------------------------------------------------------------------
    |
    | A shared secret used by LanShout to authenticate requests against
    | the LanCore API. Keep this value secret.
    |
    */

    'token' => env('LANCORE_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | SSO Settings
    |--------------------------------------------------------------------------
    |
    | The app slug registered in LanCore for this integration, and the
    | callback URL that LanCore redirects the browser back to after
    | the user authenticates.
    |
    */

    'app_slug' => env('LANCORE_APP_SLUG', 'lanshout'),

    'callback_url' => env('LANCORE_CALLBACK_URL'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Settings
    |--------------------------------------------------------------------------
    |
    | Timeout and retry settings for the HTTP client that talks to LanCore.
    |
    */

    'timeout' => env('LANCORE_TIMEOUT', 5),

    'retries' => env('LANCORE_RETRIES', 2),

    'retry_delay' => env('LANCORE_RETRY_DELAY', 100),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    |
    | The shared secret used to verify the HMAC-SHA256 signature on incoming
    | webhook requests from LanCore. Leave empty to skip verification.
    |
    */

    'webhook_secret' => env('LANCORE_WEBHOOK_SECRET'),

];
