<?php

declare(strict_types=1);

namespace App\Modules\Roles\Application\UseCases\DeleteRole;

use App\Common\Audit\AuditLoggerInterface;
use App\Common\Audit\Events\RoleAuditEvent;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Roles\Domain\Exceptions\SystemRoleViolationException;

class DeleteRoleUseCase
{
    /** Actors authorised to delete roles, in descending authority order. */
    private const ALLOWED_ACTORS = ['owner', 'school_manager', 'director'];

    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Soft-delete a role.
     * System roles cannot be deleted.
     * Only owner, school_manager, and director may delete roles.
     * Director cannot delete gestor or owner roles.
     *
     * @throws RoleNotFoundException
     * @throws SystemRoleViolationException
     * @throws HierarchyViolationException
     */
    public function execute(DeleteRoleInput $input): void
    {
        if (! in_array($input->actorSlug, self::ALLOWED_ACTORS, true)) {
            throw new HierarchyViolationException(
                'Only owner, school_manager, or director can delete roles.'
            );
        }

        $role = $this->roles->findByUuid($input->uuid);

        if ($role === null || $role->isDeleted()) {
            throw new RoleNotFoundException;
        }

        if ($role->isSystemRole()) {
            throw new SystemRoleViolationException('System roles cannot be deleted.');
        }

        // Director cannot delete gestor or owner roles.
        if ($input->actorSlug === 'director' && in_array($role->getSlug(), ['owner', 'school_manager'], true)) {
            throw new HierarchyViolationException(
                'Director cannot delete owner or school_manager roles.'
            );
        }

        $this->roles->delete($input->uuid);

        $this->audit->log(
            action: RoleAuditEvent::ROLE_DELETE,
            userId: $input->actorUserId,
            entityId: $role->getId(),
            structBefore: [
                'id' => $role->getId(),
                'uuid' => $role->getUuid(),
                'name' => $role->getName(),
                'slug' => $role->getSlug(),
            ],
        );
    }
}
