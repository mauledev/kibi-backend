<?php

return [
    'name' => env('APP_NAME', 'School Admin'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'api_prefix' => env('API_PREFIX', 'api'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'locale' => 'es',
    'fallback_locale' => 'es',
    'frontend_url' => env('APP_URL_FRONTEND', 'http://{APP_TENANT}.localhost:5173'),
    'support_address' => env('MAIL_SUPPORT_ADDRESS', env('MAIL_FROM_ADDRESS', 'support@kibi.com')),
    'support_name' => env('MAIL_SUPPORT_NAME', env('APP_NAME', 'Kibi')),
];
