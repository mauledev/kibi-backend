<?php

namespace App\Modules\Roles\Application\UseCases\ConfigureCustomRoleLimit;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use InvalidArgumentException;

class ConfigureCustomRoleLimitUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Set the maximum number of custom roles a tenant can create.
     * The caller (controller) must ensure only the owner can reach this UseCase.
     *
     * @throws InvalidArgumentException when limit is outside the 1–50 range.
     */
    public function execute(ConfigureCustomRoleLimitInput $input): void
    {
        if ($input->limit < 1 || $input->limit > 50) {
            throw new InvalidArgumentException('Custom roles limit must be between 1 and 50.');
        }

        $this->roles->setCustomRolesLimit($input->tenantId, $input->limit);

        $this->audit->log(
            action: 'tenant.custom_roles_limit.update',
            userId: $input->actorUserId,
            entityId: $input->tenantId,
            structAfter: ['custom_roles_limit' => $input->limit],
        );
    }
}
