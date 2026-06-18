<?php

namespace App\Modules\Schools\Application\UseCases\DeactivateSchool;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Schools\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Schools\Domain\Exceptions\SchoolNotFoundException;

final class DeactivateSchoolUseCase
{
    public function __construct(
        private readonly SchoolRepositoryInterface $repository,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Soft-delete a school. After this call the school is hidden from every
     * other repository query — re-deactivating an already-deactivated school
     * surfaces as a "not found" because the lookup excludes soft-deleted rows.
     *
     * @throws SchoolNotFoundException
     */
    public function execute(DeactivateSchoolInput $input): void
    {
        $school = $this->repository->findByUuid($input->uuid);

        if ($school === null) {
            throw SchoolNotFoundException::withUuid($input->uuid);
        }

        $this->repository->softDelete($school->getId());

        $this->audit->log(
            action: 'school.deactivate',
            userId: $input->actorUserId,
            entityId: $school->getId(),
            structBefore: ['uuid' => $school->getUuid(), 'deleted_at' => null],
            structAfter: ['uuid' => $school->getUuid(), 'deleted_at' => (new \DateTimeImmutable)->format('c')],
        );
    }
}
