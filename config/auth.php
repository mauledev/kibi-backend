<?php

use App\Models\User;

return [
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'api' => [
            'driver' => 'token',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
    ],

    // Login rate limiting — limits for the "login" named limiter (see AppServiceProvider).
    // Previously hardcoded in routes/api.php as throttle:5,15.
    // max_attempts applies per credential (tenant + email + ip); ip_max_attempts is
    // the per-IP backstop against email spraying — keep it well above max_attempts,
    // a school NAT can legitimately funnel many users through one IP.
    'login_throttle' => [
        'max_attempts' => (int) env('AUTH_LOGIN_MAX_ATTEMPTS', 5),
        'decay_minutes' => (int) env('AUTH_LOGIN_DECAY_MINUTES', 15),
        'ip_max_attempts' => (int) env('AUTH_LOGIN_IP_MAX_ATTEMPTS', 30),
    ],
];
