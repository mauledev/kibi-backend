<?php

namespace App\Modules\Tutor\Application\UseCases\GetTutor;

use App\Modules\Tutor\Domain\Contracts\TutorRepositoryInterface;
use App\Modules\Tutor\Domain\Entities\Tutor;
use App\Modules\Tutor\Domain\Exceptions\TutorNotFoundException;

/**
 * Retrieve a single tutor by their user UUID within the current tenant scope.
 */
final class GetTutorUseCase
{
    public function __construct(
        private readonly TutorRepositoryInterface $repository,
    ) {}

    /**
     * Execute the use case.
     *
     * @throws TutorNotFoundException When no tutor exists for the given user UUID.
     */
    public function execute(GetTutorInput $input): Tutor
    {
        $tutor = $this->repository->findByUserUuid($input->userUuid);

        if ($tutor === null) {
            throw new TutorNotFoundException($input->userUuid);
        }

        return $tutor;
    }
}
