<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Responsible Use Policy (PUR)
    |--------------------------------------------------------------------------
    |
    | Privileged accounts must accept the Responsible Use Policy before they can
    | use the app (RNF-COMPLIANCE-LFPDPPP-05). Acceptance is recorded
    | per user + version in `user_policy_acceptances`.
    |
    | `version`        Current policy version. Bump it when the policy TEXT changes
    |                  (the text itself lives in the frontend) — every required user
    |                  must then accept again. Overridable per environment via
    |                  PUR_VERSION.
    | `required_roles` Role slugs obligated to accept. Users without any of these
    |                  roles are never prompted nor blocked.
    */
    'pur' => [
        'version' => env('PUR_VERSION', '1.0'),
        'required_roles' => ['superadmin'],
    ],
];
