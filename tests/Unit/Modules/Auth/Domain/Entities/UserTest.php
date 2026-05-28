<?php

use App\Modules\Auth\Domain\Entities\User;

describe('User entity', function () {
    function makeUser(array $overrides = []): User
    {
        return new User(
            id: $overrides['id'] ?? 1,
            uuid: $overrides['uuid'] ?? 'user-uuid-1',
            tenantId: array_key_exists('tenantId', $overrides) ? $overrides['tenantId'] : 10,
            email: $overrides['email'] ?? 'test@example.com',
            fullName: $overrides['fullName'] ?? 'Test User',
            passwordHash: array_key_exists('passwordHash', $overrides) ? $overrides['passwordHash'] : 'hashed_password',
            status: $overrides['status'] ?? 'active',
            googleId: $overrides['googleId'] ?? null,
            microsoftId: $overrides['microsoftId'] ?? null,
        );
    }

    it('exposes all read properties correctly', function () {
        $user = makeUser();

        expect($user->getId())->toBe(1);
        expect($user->getUuid())->toBe('user-uuid-1');
        expect($user->getTenantId())->toBe(10);
        expect($user->getEmail())->toBe('test@example.com');
        expect($user->getFullName())->toBe('Test User');
        expect($user->getPasswordHash())->toBe('hashed_password');
        expect($user->getStatus())->toBe('active');
        expect($user->getGoogleId())->toBeNull();
        expect($user->getMicrosoftId())->toBeNull();
    });

    it('isActive returns true when status is active', function () {
        $user = makeUser(['status' => 'active']);

        expect($user->isActive())->toBeTrue();
    });

    it('isActive returns false when status is inactive', function () {
        $user = makeUser(['status' => 'inactive']);

        expect($user->isActive())->toBeFalse();
    });

    it('isStaff returns true when tenantId is null', function () {
        $user = makeUser(['tenantId' => null]);

        expect($user->isStaff())->toBeTrue();
    });

    it('isStaff returns false when tenantId is set', function () {
        $user = makeUser(['tenantId' => 5]);

        expect($user->isStaff())->toBeFalse();
    });

    it('deactivate sets status to inactive and updates updatedAt', function () {
        $user = makeUser(['status' => 'active']);

        $user->deactivate();

        expect($user->getStatus())->toBe('inactive');
        expect($user->isActive())->toBeFalse();
        expect($user->getUpdatedAt())->not->toBeNull();
    });

    it('activate sets status to active and updates updatedAt', function () {
        $user = makeUser(['status' => 'inactive']);

        $user->activate();

        expect($user->getStatus())->toBe('active');
        expect($user->isActive())->toBeTrue();
        expect($user->getUpdatedAt())->not->toBeNull();
    });

    it('changePassword updates the password hash and updatedAt', function () {
        $user = makeUser(['passwordHash' => 'old_hash']);

        $user->changePassword('new_hash');

        expect($user->getPasswordHash())->toBe('new_hash');
        expect($user->getUpdatedAt())->not->toBeNull();
    });

    it('getUpdatedAt is null before any mutation', function () {
        $user = makeUser();

        expect($user->getUpdatedAt())->toBeNull();
    });

    it('allows null password hash for OAuth users', function () {
        $user = makeUser(['passwordHash' => null]);

        expect($user->getPasswordHash())->toBeNull();
    });

    it('stores google id when provided', function () {
        $user = makeUser(['googleId' => 'google-123']);

        expect($user->getGoogleId())->toBe('google-123');
    });

    it('stores microsoft id when provided', function () {
        $user = makeUser(['microsoftId' => 'ms-456']);

        expect($user->getMicrosoftId())->toBe('ms-456');
    });

    it('exposes createdAt as DateTime instance', function () {
        $user = makeUser();

        expect($user->getCreatedAt())->toBeInstanceOf(DateTime::class);
    });
});
