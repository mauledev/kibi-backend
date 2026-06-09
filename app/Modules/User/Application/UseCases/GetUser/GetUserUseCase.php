<?php

namespace App\Modules\User\Application\UseCases\GetUser;

use App\Modules\User\Domain\Contracts\UserRepositoryInterface;
use App\Modules\User\Domain\Entities\User;
use App\Modules\User\Domain\Exceptions\UserNotFoundException;

/**
 * Returns a single tenant user by their public UUID.
 *
 * This use case is read-only — it has no side effects.
 * Throws UserNotFoundException when the UUID resolves to no row within the
 * current tenant scope. The controller maps this exception to a 404 response.
 */
final class GetUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
    ) {}

    /**
     * Execute the use case.
     *
     * @throws UserNotFoundException When no user with the given UUID exists in the current tenant.
     */
    public function execute(GetUserInput $input): User
    {
        $user = $this->repository->findByUuid($input->uuid);

        if ($user === null) {
            throw new UserNotFoundException($input->uuid);
        }

        return $user;
    }
}
