<?php

namespace App\Modules\Schools\Application\UseCases\UpdateSchool;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Schools\Domain\Contracts\SchoolRepositoryInterface;
use App\Modules\Schools\Domain\Entities\School;
use App\Modules\Schools\Domain\Exceptions\SchoolNotFoundException;

final class UpdateSchoolUseCase
{
    public function __construct(
        private readonly SchoolRepositoryInterface $repository,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Apply partial updates to a school. Only fields flagged as present in
     * the input are mutated; the rest stay as they were. Slug and status are
     * intentionally not updatable here — slug is locked because it is the
     * school's public identifier, and status moves through dedicated
     * suspend/activate endpoints.
     *
     * @throws SchoolNotFoundException
     */
    public function execute(UpdateSchoolInput $input): School
    {
        $school = $this->repository->findByUuid($input->uuid);

        if ($school === null || $school->isDeleted()) {
            throw SchoolNotFoundException::withUuid($input->uuid);
        }

        $before = $this->schoolToArray($school);

        if ($input->hasName && $input->name !== null) {
            $school->rename($input->name);
        }

        if ($input->hasPhone) {
            $school->updatePhone($input->phone);
        }

        if ($input->hasAddress) {
            $school->updateAddress($input->address);
        }

        $updated = $this->repository->update($school);

        $this->audit->log(
            action: 'school.update',
            userId: $input->actorUserId,
            entityId: $updated->getId(),
            structBefore: $before,
            structAfter: $this->schoolToArray($updated),
        );

        return $updated;
    }

    /** @return array<string, mixed> */
    private function schoolToArray(School $school): array
    {
        return [
            'uuid' => $school->getUuid(),
            'name' => $school->getName(),
            'slug' => $school->getSlug(),
            'phone' => $school->getPhone(),
            'address' => $school->getAddress(),
            'status' => $school->getStatus(),
        ];
    }
}
