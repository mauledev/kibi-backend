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
     * Soft-delete a custom role.
     * Only custom roles (tenant-scoped, no category, not owner/school_manager) can be deleted.
     * Only owner, school_manager, and director may delete roles.
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

        if (! $role->isCustomRole()) {
            throw new SystemRoleViolationException('Only custom roles can be deleted.');
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
