<?php

use App\Modules\User\Domain\Entities\RoleAssignment;
use App\Modules\User\Domain\Entities\User;

describe('User entity', function () {
    /**
     * Build a User entity with sensible defaults; override via $overrides.
     */
    function makeUserEntity(array $overrides = []): User
    {
        return new User(
            id: $overrides['id'] ?? 1,
            uuid: $overrides['uuid'] ?? 'user-uuid-1',
            email: $overrides['email'] ?? 'test@example.com',
            firstName: $overrides['firstName'] ?? 'Juan',
            lastNamePaternal: $overrides['lastNamePaternal'] ?? 'García',
            lastNameMaternal: array_key_exists('lastNameMaternal', $overrides)
                ? $overrides['lastNameMaternal']
                : null,
            phone: array_key_exists('phone', $overrides) ? $overrides['phone'] : null,
            status: $overrides['status'] ?? 'active',
            createdAt: $overrides['createdAt'] ?? new DateTime,
            emailVerifiedAt: array_key_exists('emailVerifiedAt', $overrides)
                ? $overrides['emailVerifiedAt']
                : null,
            roles: $overrides['roles'] ?? [],
        );
    }

    describe('getters return constructor values', function () {
        it('exposes id', function () {
            $user = makeUserEntity(['id' => 42]);

            expect($user->getId())->toBe(42);
        });

        it('exposes uuid', function () {
            $user = makeUserEntity(['uuid' => 'abc-123']);

            expect($user->getUuid())->toBe('abc-123');
        });

        it('exposes email', function () {
            $user = makeUserEntity(['email' => 'juan@example.com']);

            expect($user->getEmail())->toBe('juan@example.com');
        });

        it('exposes first_name', function () {
            $user = makeUserEntity(['firstName' => 'Mauricio']);

            expect($user->getFirstName())->toBe('Mauricio');
        });

        it('exposes last_name_paternal', function () {
            $user = makeUserEntity(['lastNamePaternal' => 'Ledesma']);

            expect($user->getLastNamePaternal())->toBe('Ledesma');
        });

        it('exposes last_name_maternal as null when not provided', function () {
            $user = makeUserEntity(['lastNameMaternal' => null]);

            expect($user->getLastNameMaternal())->toBeNull();
        });

        it('exposes last_name_maternal when provided', function () {
            $user = makeUserEntity(['lastNameMaternal' => 'García']);

            expect($user->getLastNameMaternal())->toBe('García');
        });

        it('exposes phone as null when not provided', function () {
            $user = makeUserEntity(['phone' => null]);

            expect($user->getPhone())->toBeNull();
        });

        it('exposes phone when provided', function () {
            $user = makeUserEntity(['phone' => '+52 55 1234 5678']);

            expect($user->getPhone())->toBe('+52 55 1234 5678');
        });

        it('exposes status', function () {
            $user = makeUserEntity(['status' => 'inactive']);

            expect($user->getStatus())->toBe('inactive');
        });

        it('exposes createdAt as a DateTime instance', function () {
            $user = makeUserEntity();

            expect($user->getCreatedAt())->toBeInstanceOf(DateTime::class);
        });

        it('exposes roles provided at construction', function () {
            $roles = [
                new RoleAssignment(roleUuid: 'role-uuid-student', slug: 'student', name: 'Student', schoolUuid: 'school-uuid-1'),
                new RoleAssignment(roleUuid: 'role-uuid-tutor', slug: 'tutor', name: 'Tutor', schoolUuid: null),
            ];

            $user = makeUserEntity(['roles' => $roles]);

            expect($user->getRoles())->toBe($roles);
        });
    });

    describe('getFullName', function () {
        it('concatenates first name and paternal last name when maternal is null', function () {
            $user = makeUserEntity([
                'firstName' => 'Juan',
                'lastNamePaternal' => 'García',
                'lastNameMaternal' => null,
            ]);

            expect($user->getFullName())->toBe('Juan García');
        });

        it('includes maternal last name when not null', function () {
            $user = makeUserEntity([
                'firstName' => 'Mauricio',
                'lastNamePaternal' => 'Ledesma',
                'lastNameMaternal' => 'García',
            ]);

            expect($user->getFullName())->toBe('Mauricio Ledesma García');
        });

        it('omits maternal last name when it is null', function () {
            $user = makeUserEntity([
                'firstName' => 'Ana',
                'lastNamePaternal' => 'Torres',
                'lastNameMaternal' => null,
            ]);

            expect($user->getFullName())->not->toContain('null');
            expect($user->getFullName())->toBe('Ana Torres');
        });
    });

    describe('getRoles', function () {
        it('returns an empty array when no roles are provided', function () {
            $user = makeUserEntity(['roles' => []]);

            expect($user->getRoles())->toBeArray()->toBeEmpty();
        });

        it('returns each RoleAssignment with slug, name, and schoolUuid', function () {
            $roles = [
                new RoleAssignment(roleUuid: 'role-uuid-teacher', slug: 'teacher', name: 'Teacher', schoolUuid: 'school-uuid-abc'),
            ];

            $user = makeUserEntity(['roles' => $roles]);

            $result = $user->getRoles();

            expect($result)->toHaveCount(1);
            expect($result[0]->slug)->toBe('teacher');
            expect($result[0]->name)->toBe('Teacher');
            expect($result[0]->schoolUuid)->toBe('school-uuid-abc');
        });

        it('returns multiple RoleAssignment objects unchanged', function () {
            $roles = [
                new RoleAssignment(roleUuid: 'role-uuid-student', slug: 'student', name: 'Student', schoolUuid: 'school-uuid-1'),
                new RoleAssignment(roleUuid: 'role-uuid-tutor', slug: 'tutor', name: 'Tutor', schoolUuid: null),
            ];

            $user = makeUserEntity(['roles' => $roles]);

            expect($user->getRoles())->toHaveCount(2);
            expect($user->getRoles()[1]->slug)->toBe('tutor');
            expect($user->getRoles()[1]->schoolUuid)->toBeNull();
        });
    });
});
