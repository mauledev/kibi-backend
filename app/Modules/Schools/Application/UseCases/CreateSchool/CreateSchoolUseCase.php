<?php

namespace App\Modules\Schools\Application\UseCases\CreateSchool;

use App\Common\Audit\AuditLoggerInterface;
use App\Common\Tenant\TenantContext;
use App\Modules\Schools\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Schools\Domain\Entities\School;
use App\Modules\Schools\Domain\Exceptions\SchoolAlreadyExistsException;

final class CreateSchoolUseCase
{
    public function __construct(
        private readonly SchoolRepositoryInterface $repository,
        private readonly TenantContext $context,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Create a new school within the current tenant. New schools always start
     * in the 'active' status — status transitions happen through dedicated
     * suspend/activate endpoints.
     *
     * @throws SchoolAlreadyExistsException
     */
    public function execute(CreateSchoolInput $input): School
    {
        if ($this->repository->existsBySlug($input->slug)) {
            throw SchoolAlreadyExistsException::withSlug($input->slug, $this->context->tenantId);
        }

        $school = $this->repository->create(
            name: $input->name,
            slug: $input->slug,
            address: $input->address,
            phone: $input->phone,
            status: 'active',
        );

        $this->audit->log(
            action: 'school.create',
            userId: $input->actorUserId,
            entityId: $school->getId(),
            structAfter: $this->schoolToArray($school),
        );

        return $school;
    }

    /** @return array<string, mixed> */
    private function schoolToArray(School $school): array
    {
        return [
            'id' => $school->getId(),
            'uuid' => $school->getUuid(),
            'tenant_id' => $school->getTenantId(),
            'name' => $school->getName(),
            'slug' => $school->getSlug(),
            'address' => $school->getAddress(),
            'phone' => $school->getPhone(),
            'status' => $school->getStatus(),
        ];
    }
}
