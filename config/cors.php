<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | Configures the HandleCors middleware from fruitcake/laravel-cors, which
    | Laravel bundles by default. Paths, origins, methods and headers listed
    | here are what the middleware uses to generate CORS response headers.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    /*
     * FRONTEND_URL is the canonical production/staging origin (e.g. https://app.kibi.com).
     * array_filter removes the empty string when the variable is not set, so the
     * patterns array below handles local dev without a hard-coded origin.
     */
    'allowed_origins' => array_filter([env('FRONTEND_URL')]),

    'allowed_origins_patterns' => [
        '#^https?://[^.]+\.localhost(:\d+)?$#',   // local dev wildcard subdomains
    ],

    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Tenant-Slug'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,

];
