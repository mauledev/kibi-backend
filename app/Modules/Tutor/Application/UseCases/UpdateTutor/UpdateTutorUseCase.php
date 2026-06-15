<?php

namespace App\Modules\Tutor\Application\UseCases\UpdateTutor;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Tutor\Domain\Contracts\TutorRepositoryInterface;
use App\Modules\Tutor\Domain\Entities\Tutor;
use App\Modules\Tutor\Domain\Exceptions\TutorNotFoundException;
use App\Modules\Tutor\Domain\ValueObjects\TutorUpdateData;

/**
 * Update a tutor's identity and profile fields.
 *
 * Only non-null fields in the input are applied. After update an audit log
 * entry is written capturing the state after the change.
 */
final class UpdateTutorUseCase
{
    public function __construct(
        private readonly TutorRepositoryInterface $repository,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Execute the use case.
     *
     * @throws TutorNotFoundException When the tutor does not exist within the current tenant.
     */
    public function execute(UpdateTutorInput $input): Tutor
    {
        $tutor = $this->repository->findByUserUuid($input->userUuid);

        if ($tutor === null) {
            throw new TutorNotFoundException($input->userUuid);
        }

        $updated = $this->repository->update(
            $tutor->getUserId(),
            new TutorUpdateData(
                firstName: $input->firstName,
                lastNamePaternal: $input->lastNamePaternal,
                lastNameMaternal: $input->lastNameMaternal,
                phone: $input->phone,
                occupation: $input->occupation,
            )
        );

        $this->audit->log(
            action: 'tutor.update',
            userId: $updated->getUserId(),
            entityId: $updated->getId(),
            structBefore: [
                'full_name' => $tutor->getFullName(),
                'phone' => $tutor->getPhone(),
                'occupation' => $tutor->getOccupation(),
            ],
            structAfter: [
                'full_name' => $updated->getFullName(),
                'phone' => $updated->getPhone(),
                'occupation' => $updated->getOccupation(),
            ],
        );

        return $updated;
    }
}
