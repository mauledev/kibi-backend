<?php

namespace App\Modules\Student\Domain\Contracts;

use App\Modules\Student\Domain\Criteria\StudentListCriteria;
use App\Modules\Student\Domain\Entities\Student;
use App\Modules\Student\Domain\ValueObjects\StudentUpdateData;

/**
 * Repository contract for the Student bounded context.
 *
 * All methods scope to the current tenant via TenantContext.
 * Eloquent models never leave the implementation — every method returns
 * a Domain Entity or a primitive.
 */
interface StudentRepositoryInterface
{
    /**
     * Create a new student profile linked to the given user.
     *
     * The user is identified by UUID. The repository resolves the internal user_id
     * internally. Returns the fully populated Student entity.
     */
    public function create(
        string $userUuid,
        ?string $birthDate,
        ?string $nationalId,
        ?string $enrollmentNumber,
        ?string $gender,
        ?string $bloodType,
        ?int $groupId,
    ): Student;

    /**
     * Update the identity fields on the users table and the profile fields on
     * student_profiles for the student identified by user ID.
     *
     * Returns the updated Student entity.
     */
    public function update(int $userId, StudentUpdateData $data): Student;

    /**
     * Return a paginated list of students matching the given criteria.
     *
     * The returned array always contains the keys: items, total, per_page,
     * current_page, last_page.
     *
     * @return array{items: Student[], total: int, per_page: int, current_page: int, last_page: int}
     */
    public function findAllPaginated(StudentListCriteria $criteria): array;

    /**
     * Find a student by the associated user's public UUID.
     *
     * Returns null when no student with the given user UUID exists within the
     * current tenant scope.
     */
    public function findByUserUuid(string $uuid): ?Student;
}
