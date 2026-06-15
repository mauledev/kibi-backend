<?php

namespace App\Modules\Student\Application\UseCases\GetStudent;

use App\Modules\Student\Domain\Contracts\StudentRepositoryInterface;
use App\Modules\Student\Domain\Entities\Student;
use App\Modules\Student\Domain\Exceptions\StudentNotFoundException;

/**
 * Returns a single student by the associated user's public UUID.
 *
 * This use case is read-only — it has no side effects.
 * Throws StudentNotFoundException when the UUID resolves to no row within the
 * current tenant scope. The controller maps this exception to a 404 response.
 */
final class GetStudentUseCase
{
    public function __construct(
        private readonly StudentRepositoryInterface $repository,
    ) {}

    /**
     * Execute the use case.
     *
     * @throws StudentNotFoundException When no student with the given user UUID exists in the current tenant.
     */
    public function execute(GetStudentInput $input): Student
    {
        $student = $this->repository->findByUserUuid($input->userUuid);

        if ($student === null) {
            throw new StudentNotFoundException($input->userUuid);
        }

        return $student;
    }
}
