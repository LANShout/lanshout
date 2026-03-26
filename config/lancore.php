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
    | HTTP Client Settings
    |--------------------------------------------------------------------------
    |
    | Timeout and retry settings for the HTTP client that talks to LanCore.
    |
    */

    'timeout' => env('LANCORE_TIMEOUT', 5),

    'retries' => env('LANCORE_RETRIES', 2),

    'retry_delay' => env('LANCORE_RETRY_DELAY', 100),

];
