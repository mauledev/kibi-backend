<?php

namespace App\Modules\Tutor\Domain\Contracts;

use App\Modules\Tutor\Domain\Criteria\TutorListCriteria;
use App\Modules\Tutor\Domain\Entities\Tutor;
use App\Modules\Tutor\Domain\ValueObjects\TutorUpdateData;

/**
 * Repository contract for the Tutor bounded context.
 *
 * All methods scope to the current tenant via TenantContext.
 * Eloquent models never leave the implementation — every method returns
 * a Domain Entity or a primitive.
 */
interface TutorRepositoryInterface
{
    /**
     * Create a new tutor profile linked to the given user.
     *
     * The user is identified by UUID. The repository resolves the internal user_id
     * internally. Returns the fully populated Tutor entity.
     */
    public function create(string $userUuid, ?string $occupation): Tutor;

    /**
     * Update the identity fields on the users table and the profile fields on
     * tutor_profiles for the tutor identified by user ID.
     *
     * Returns the updated Tutor entity.
     */
    public function update(int $userId, TutorUpdateData $data): Tutor;

    /**
     * Return a paginated list of tutors matching the given criteria.
     *
     * The returned array always contains the keys: items, total, per_page,
     * current_page, last_page.
     *
     * @return array{items: Tutor[], total: int, per_page: int, current_page: int, last_page: int}
     */
    public function findAllPaginated(TutorListCriteria $criteria): array;

    /**
     * Find a tutor by the associated user's public UUID.
     *
     * Returns null when no tutor with the given user UUID exists within the
     * current tenant scope.
     */
    public function findByUserUuid(string $uuid): ?Tutor;

    /**
     * Return true when the given student user already has at least one active tutor link.
     *
     * Used to determine whether to send a magic link to the student on new tutor-student link creation.
     */
    public function hasActiveLink(int $studentUserId): bool;

    /**
     * Create a link between a tutor and a student in the student_tutors table.
     */
    public function linkToStudent(int $tutorUserId, int $studentUserId, ?string $relationship): void;
}
