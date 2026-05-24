<?php

use App\Modules\Roles\Domain\Entities\UserRoleAssignment;

describe('UserRoleAssignment entity', function () {
    function makeAssignment(array $overrides = []): UserRoleAssignment
    {
        return new UserRoleAssignment(
            id: $overrides['id'] ?? 1,
            userId: $overrides['userId'] ?? 10,
            roleId: $overrides['roleId'] ?? 5,
            schoolId: array_key_exists('schoolId', $overrides) ? $overrides['schoolId'] : null,
            assignedBy: array_key_exists('assignedBy', $overrides) ? $overrides['assignedBy'] : 2,
            assignedAt: $overrides['assignedAt'] ?? new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            revokedAt: array_key_exists('revokedAt', $overrides) ? $overrides['revokedAt'] : null,
        );
    }

    it('exposes all read properties correctly', function () {
        $assignment = makeAssignment([
            'id' => 42,
            'userId' => 10,
            'roleId' => 5,
            'schoolId' => 3,
            'assignedBy' => 2,
        ]);

        expect($assignment->getId())->toBe(42);
        expect($assignment->getUserId())->toBe(10);
        expect($assignment->getRoleId())->toBe(5);
        expect($assignment->getSchoolId())->toBe(3);
        expect($assignment->getAssignedBy())->toBe(2);
        expect($assignment->getRevokedAt())->toBeNull();
    });

    it('isActive returns true when revokedAt is null', function () {
        $assignment = makeAssignment(['revokedAt' => null]);

        expect($assignment->isActive())->toBeTrue();
    });

    it('isActive returns false when revokedAt is set', function () {
        $assignment = makeAssignment([
            'revokedAt' => new DateTimeImmutable('2025-06-01T12:00:00+00:00'),
        ]);

        expect($assignment->isActive())->toBeFalse();
    });

    it('revoke sets revokedAt to a non-null DateTimeImmutable', function () {
        $assignment = makeAssignment(['revokedAt' => null]);

        expect($assignment->isActive())->toBeTrue();

        $assignment->revoke();

        expect($assignment->isActive())->toBeFalse();
        expect($assignment->getRevokedAt())->toBeInstanceOf(DateTimeImmutable::class);
    });

    it('revoke can be called when already revoked without error', function () {
        $existingRevocation = new DateTimeImmutable('2025-01-01');
        $assignment = makeAssignment(['revokedAt' => $existingRevocation]);

        // Calling revoke again overwrites with a new timestamp
        $assignment->revoke();

        expect($assignment->isActive())->toBeFalse();
        expect($assignment->getRevokedAt())->not->toBeNull();
    });

    it('supports null schoolId for tenant-level assignments', function () {
        $assignment = makeAssignment(['schoolId' => null]);

        expect($assignment->getSchoolId())->toBeNull();
    });

    it('supports null assignedBy for system-created assignments', function () {
        $assignment = makeAssignment(['assignedBy' => null]);

        expect($assignment->getAssignedBy())->toBeNull();
    });

    it('exposes assignedAt as DateTimeImmutable', function () {
        $date = new DateTimeImmutable('2024-06-15T10:30:00+00:00');
        $assignment = makeAssignment(['assignedAt' => $date]);

        expect($assignment->getAssignedAt())->toBeInstanceOf(DateTimeImmutable::class);
        expect($assignment->getAssignedAt()->format('Y-m-d'))->toBe('2024-06-15');
    });
});
