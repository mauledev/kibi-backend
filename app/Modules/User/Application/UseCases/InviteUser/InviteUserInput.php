<?php

namespace App\Modules\User\Application\UseCases\InviteUser;

/**
 * Input for inviting a tenant user.
 *
 * The user is created in a pending state (password null, email_verified_at null)
 * within the current tenant, the given role/school assignments are applied, and a
 * signed activation (magic link) email is sent — the same activation mechanism the
 * owner uses when a tenant is created.
 *
 * @param  array<int, array{roleUuid: string, schoolUuid: string|null}>  $assignments
 *                                                                                     One entry per (role, school) to grant. schoolUuid is null for tenant-level roles.
 */
final class InviteUserInput
{
    /**
     * @param  array<int, array{roleUuid: string, schoolUuid: string|null}>  $assignments
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly string $tenantSlug,
        public readonly string $actorUuid,
        public readonly string $actorSlug,
        public readonly string $email,
        public readonly string $firstName,
        public readonly string $lastNamePaternal,
        public readonly ?string $lastNameMaternal,
        public readonly array $assignments,
    ) {}
}
