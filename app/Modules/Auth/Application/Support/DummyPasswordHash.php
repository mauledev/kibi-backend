<?php

namespace App\Modules\Auth\Application\Support;

/**
 * Constant bcrypt hash used to equalize login response timing.
 *
 * When the user does not exist or has no password (OAuth-only accounts,
 * password_hash NULL), the login use cases still run one full bcrypt
 * verification against this hash, so response time never reveals whether
 * an email is registered (anti user-enumeration timing oracle).
 *
 * The preimage was a discarded random string: this hash can never match
 * a real password. The embedded cost MUST stay in sync with the cost of
 * real password hashes (bcrypt 12 — see docs/global-rules.md and the
 * BCRYPT_ROUNDS default). If the cost ever changes, regenerate with:
 *
 *   php -r "echo password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT, ['cost' => 12]);"
 */
final class DummyPasswordHash
{
    public const BCRYPT = '$2y$12$4LWcHhxt9pTpEs/KYYGm8.CMe9.0ByupSNd/TwEFfJxNvF9..vLVC';
}
