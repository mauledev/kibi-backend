<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Which roles mandate two-factor
    |--------------------------------------------------------------------------
    |
    | Whether a role forces 2FA is stored per-role in the database
    | (`roles.requires_2fa`) — the single source of truth, queried by the login
    | and activation gates. There is intentionally no role list here.
    |
    | Issuer shown in the authenticator app.
    */
    'issuer' => env('APP_NAME', 'Kibi'),

    /*
    |--------------------------------------------------------------------------
    | Login challenge TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | How long the short-lived 2FA challenge token (returned by /staff/auth/login
    | when 2FA is pending) stays valid before the user must log in again.
    */
    'challenge_ttl' => 600,
];
