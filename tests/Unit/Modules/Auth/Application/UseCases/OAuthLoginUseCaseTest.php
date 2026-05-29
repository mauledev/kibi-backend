<?php

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Auth\Application\DTOs\LoginOutput;
use App\Modules\Auth\Application\DTOs\OAuthLoginInput;
use App\Modules\Auth\Application\DTOs\OAuthUserData;
use App\Modules\Auth\Application\UseCases\OAuthLogin\OAuthLoginUseCase;
use App\Modules\Auth\Domain\Contracts\OAuthProviderInterface;
use App\Modules\Auth\Domain\Contracts\TokenServiceInterface;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Entities\User;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;

describe('OAuthLoginUseCase', function () {
    beforeEach(function () {
        $this->provider = Mockery::mock(OAuthProviderInterface::class);
        $this->userRepo = Mockery::mock(UserRepositoryInterface::class);
        $this->tokens = Mockery::mock(TokenServiceInterface::class);
        $this->roleRepo = Mockery::mock(RoleRepositoryInterface::class);
        $this->audit = Mockery::mock(AuditLoggerInterface::class);
        $this->useCase = new OAuthLoginUseCase(
            $this->provider,
            $this->userRepo,
            $this->tokens,
            $this->roleRepo,
            $this->audit,
        );
    });

    afterEach(function () {
        Mockery::close();
    });

    function oauthMakeUser(array $overrides = []): User
    {
        return new User(
            id: $overrides['id'] ?? 1,
            uuid: $overrides['uuid'] ?? 'user-uuid',
            isStaff: $overrides['isStaff'] ?? false,
            email: $overrides['email'] ?? 'user@gmail.com',
            firstName: $overrides['firstName'] ?? 'OAuth',
            lastNamePaternal: $overrides['lastNamePaternal'] ?? 'User',
            lastNameMaternal: $overrides['lastNameMaternal'] ?? null,
            passwordHash: null,
            status: 'active',
            googleId: $overrides['googleId'] ?? null,
            microsoftId: $overrides['microsoftId'] ?? null,
        );
    }

    function oauthUserData(array $overrides = []): OAuthUserData
    {
        return new OAuthUserData(
            providerId: $overrides['providerId'] ?? 'google-123',
            email: $overrides['email'] ?? 'user@gmail.com',
            name: $overrides['name'] ?? 'OAuth User',
        );
    }

    it('finds existing user by google_id and returns LoginOutput', function () {
        $oauthData = oauthUserData();
        $user = oauthMakeUser(['googleId' => 'google-123']);

        $this->provider->shouldReceive('getUserFromToken')->once()->with('access-token')->andReturn($oauthData);
        $this->userRepo->shouldReceive('findByGoogleId')->once()->with('google-123')->andReturn($user);
        $this->roleRepo->shouldReceive('findActiveRolesForUser')->once()->with(1)->andReturn([]);
        $this->tokens->shouldReceive('generate')->once()->with(1)->andReturn('oauth-token');
        $this->audit->shouldReceive('log')->once();

        $input = new OAuthLoginInput(provider: 'google', accessToken: 'access-token');
        $output = $this->useCase->execute($input);

        expect($output)->toBeInstanceOf(LoginOutput::class);
        expect($output->token)->toBe('oauth-token');
    });

    it('falls back to email lookup when google_id is not found', function () {
        $oauthData = oauthUserData(['providerId' => 'google-new']);
        $user = oauthMakeUser(['email' => 'user@gmail.com']);

        $this->provider->shouldReceive('getUserFromToken')->once()->andReturn($oauthData);
        $this->userRepo->shouldReceive('findByGoogleId')->once()->with('google-new')->andReturn(null);
        $this->userRepo->shouldReceive('findByEmail')->once()->with('user@gmail.com')->andReturn($user);
        $this->roleRepo->shouldReceive('findActiveRolesForUser')->once()->andReturn([]);
        $this->tokens->shouldReceive('generate')->once()->andReturn('token');
        $this->audit->shouldReceive('log')->once();

        $input = new OAuthLoginInput(provider: 'google', accessToken: 'access-token');
        $output = $this->useCase->execute($input);

        expect($output->email)->toBe('user@gmail.com');
    });

    it('creates a new user when no google_id or email match exists', function () {
        $oauthData = oauthUserData(['providerId' => 'google-brand-new', 'email' => 'new@gmail.com']);
        $newUser = oauthMakeUser(['id' => 2, 'uuid' => 'new-uuid', 'email' => 'new@gmail.com', 'googleId' => 'google-brand-new']);

        $this->provider->shouldReceive('getUserFromToken')->once()->andReturn($oauthData);
        $this->userRepo->shouldReceive('findByGoogleId')->once()->andReturn(null);
        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn(null);
        $this->userRepo->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (User $u) => $u->getGoogleId() === 'google-brand-new' && $u->getEmail() === 'new@gmail.com'))
            ->andReturn($newUser);
        $this->roleRepo->shouldReceive('findActiveRolesForUser')->once()->andReturn([]);
        $this->tokens->shouldReceive('generate')->once()->andReturn('new-token');
        $this->audit->shouldReceive('log')->once();

        $input = new OAuthLoginInput(provider: 'google', accessToken: 'access-token');
        $output = $this->useCase->execute($input);

        expect($output->uuid)->toBe('new-uuid');
    });

    it('uses microsoft_id lookup for microsoft provider', function () {
        $oauthData = oauthUserData(['providerId' => 'ms-456']);
        $user = oauthMakeUser(['microsoftId' => 'ms-456']);

        $this->provider->shouldReceive('getUserFromToken')->once()->andReturn($oauthData);
        $this->userRepo->shouldReceive('findByMicrosoftId')->once()->with('ms-456')->andReturn($user);
        $this->roleRepo->shouldReceive('findActiveRolesForUser')->once()->andReturn([]);
        $this->tokens->shouldReceive('generate')->once()->andReturn('ms-token');
        $this->audit->shouldReceive('log')->once();

        $input = new OAuthLoginInput(provider: 'microsoft', accessToken: 'ms-access-token');
        $output = $this->useCase->execute($input);

        expect($output)->toBeInstanceOf(LoginOutput::class);
    });

    it('creates new user with microsoft_id for microsoft provider on first login', function () {
        $oauthData = oauthUserData(['providerId' => 'ms-new', 'email' => 'user@outlook.com', 'name' => 'MS User']);
        $newUser = oauthMakeUser(['id' => 5, 'uuid' => 'ms-new-uuid', 'email' => 'user@outlook.com', 'microsoftId' => 'ms-new']);

        $this->provider->shouldReceive('getUserFromToken')->once()->andReturn($oauthData);
        $this->userRepo->shouldReceive('findByMicrosoftId')->once()->andReturn(null);
        $this->userRepo->shouldReceive('findByEmail')->once()->andReturn(null);
        $this->userRepo->shouldReceive('save')
            ->once()
            ->with(Mockery::on(fn (User $u) => $u->getMicrosoftId() === 'ms-new' && $u->getGoogleId() === null))
            ->andReturn($newUser);
        $this->roleRepo->shouldReceive('findActiveRolesForUser')->once()->andReturn([]);
        $this->tokens->shouldReceive('generate')->once()->andReturn('ms-token');
        $this->audit->shouldReceive('log')->once();

        $input = new OAuthLoginInput(provider: 'microsoft', accessToken: 'ms-access-token');
        $output = $this->useCase->execute($input);

        expect($output->uuid)->toBe('ms-new-uuid');
    });

    it('writes audit log with provider info on successful oauth login', function () {
        $oauthData = oauthUserData();
        $user = oauthMakeUser();

        $this->provider->shouldReceive('getUserFromToken')->once()->andReturn($oauthData);
        $this->userRepo->shouldReceive('findByGoogleId')->once()->andReturn($user);
        $this->roleRepo->shouldReceive('findActiveRolesForUser')->once()->andReturn([]);
        $this->tokens->shouldReceive('generate')->once()->andReturn('token');
        $this->audit->shouldReceive('log')->once();

        $input = new OAuthLoginInput(provider: 'google', accessToken: 'access-token');
        $this->useCase->execute($input);
    });
});
