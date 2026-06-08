<?php

namespace App\Modules\Roles\Application\UseCases\UpdateRole;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Roles\Domain\Exceptions\SystemRoleViolationException;

class UpdateRoleUseCase
{
    /** Actors authorised to update roles, in descending authority order. */
    private const ALLOWED_ACTORS = ['owner', 'gestor_escuelas', 'director'];

    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Update mutable fields on a role.
     * Only owner, gestor_escuelas, and director may update roles.
     * System roles cannot be renamed.
     *
     * @throws RoleNotFoundException
     * @throws SystemRoleViolationException
     * @throws HierarchyViolationException
     */
    public function execute(UpdateRoleInput $input): Role
    {
        if (! in_array($input->actorSlug, self::ALLOWED_ACTORS, true)) {
            throw new HierarchyViolationException(
                'Only owner, gestor_escuelas, or director can update roles.'
            );
        }

        $role = $this->roles->findByUuid($input->uuid);

        if ($role === null || $role->isDeleted()) {
            throw new RoleNotFoundException;
        }

        if ($role->isSystemRole()) {
            throw new SystemRoleViolationException('System roles cannot be renamed.');
        }

        // Director cannot update gestor or owner roles.
        if ($input->actorSlug === 'director' && in_array($role->getSlug(), ['owner', 'gestor_escuelas'], true)) {
            throw new HierarchyViolationException(
                'Director cannot update owner or gestor_escuelas roles.'
            );
        }

        $before = $this->roleToArray($role);

        $role->rename($input->name);
        $updated = $this->roles->update($role);

        $this->audit->log(
            action: 'role.update',
            userId: $input->actorUserId,
            entityId: $role->getId(),
            structBefore: $before,
            structAfter: $this->roleToArray($updated),
        );

        return $updated;
    }

    /** @return array<string, mixed> */
    private function roleToArray(Role $role): array
    {
        return [
            'id' => $role->getId(),
            'uuid' => $role->getUuid(),
            'name' => $role->getName(),
            'slug' => $role->getSlug(),
        ];
    }
}
