<?php

namespace App\Modules\Auth\Domain\Contracts;

use App\Modules\Auth\Application\DTOs\OAuthUserData;

interface OAuthProviderInterface
{
    /**
     * Resolve user data from a provider access token.
     *
     * @throws \RuntimeException When the provider is unavailable or the token is invalid.
     */
    public function getUserFromToken(string $accessToken): OAuthUserData;
}
