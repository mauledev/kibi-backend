<?php

namespace App\Modules\User\Domain\Entities;

/**
 * Compact value object representing one active role assignment on a User entity.
 *
 * A single user can hold many of these — one per user_role_assignments row that
 * has revoked_at IS NULL. The schoolUuid is null for tenant-level roles
 * (owner, gestor) whose assignments have school_id IS NULL.
 *
 * Kept as a plain PHP class with no framework dependencies so it can be
 * constructed, compared, and serialized anywhere in the domain.
 */
final class RoleAssignment
{
    public function __construct(
        public readonly string $roleUuid,
        public readonly string $slug,
        public readonly string $name,
        public readonly ?string $schoolUuid,
    ) {}
}
