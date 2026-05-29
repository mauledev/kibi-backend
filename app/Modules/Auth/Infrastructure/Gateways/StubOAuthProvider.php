<?php

namespace App\Modules\Auth\Infrastructure\Gateways;

use App\Modules\Auth\Application\DTOs\OAuthUserData;
use App\Modules\Auth\Domain\Contracts\OAuthProviderInterface;
use RuntimeException;

/**
 * Placeholder OAuth provider used until Socialite is integrated.
 *
 * Every call throws a RuntimeException to make the absence of a real
 * implementation explicit. Replace this binding in AppServiceProvider
 * once the Socialite gateway is ready.
 */
class StubOAuthProvider implements OAuthProviderInterface
{
    /**
     * Not implemented — throws RuntimeException.
     *
     * @throws RuntimeException Always.
     */
    public function getUserFromToken(string $accessToken): OAuthUserData
    {
        throw new RuntimeException('OAuth provider not configured');
    }
}
