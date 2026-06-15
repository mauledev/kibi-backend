<?php

namespace App\Modules\Student\Application\UseCases\UpdateStudent;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Student\Domain\Contracts\StudentRepositoryInterface;
use App\Modules\Student\Domain\Entities\Student;
use App\Modules\Student\Domain\Exceptions\StudentNotFoundException;
use App\Modules\Student\Domain\ValueObjects\StudentUpdateData;
use Illuminate\Support\Facades\DB;

/**
 * Update a student's identity and profile fields, and write an audit log entry.
 *
 * The student is resolved by their user UUID. All fields are optional — only
 * provided (non-null) fields are updated. Both the users table (identity fields)
 * and student_profiles table (profile fields) may be updated in a single transaction.
 */
final class UpdateStudentUseCase
{
    public function __construct(
        private readonly StudentRepositoryInterface $students,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Execute the use case.
     *
     * @throws StudentNotFoundException When no student with the given user UUID exists.
     */
    public function execute(UpdateStudentInput $input): Student
    {
        $before = $this->students->findByUserUuid($input->userUuid);

        if ($before === null) {
            throw new StudentNotFoundException($input->userUuid);
        }

        $data = new StudentUpdateData(
            firstName: $input->firstName,
            lastNamePaternal: $input->lastNamePaternal,
            lastNameMaternal: $input->lastNameMaternal,
            phone: $input->phone,
            birthDate: $input->birthDate,
            nationalId: $input->nationalId,
            enrollmentNumber: $input->enrollmentNumber,
            gender: $input->gender,
            bloodType: $input->bloodType,
            groupId: $input->groupId,
        );

        $updated = DB::transaction(
            fn (): Student => $this->students->update($before->getUserId(), $data)
        );

        $this->audit->log(
            action: 'student.update',
            userId: $input->actorId,
            entityId: $updated->getId(),
            structBefore: [
                'user_uuid' => $before->getUserUuid(),
                'first_name' => $before->getFirstName(),
                'last_name_paternal' => $before->getLastNamePaternal(),
                'last_name_maternal' => $before->getLastNameMaternal(),
                'phone' => $before->getPhone(),
                'birth_date' => $before->getBirthDate(),
                'national_id' => $before->getNationalId(),
                'enrollment_number' => $before->getEnrollmentNumber(),
                'gender' => $before->getGender(),
                'blood_type' => $before->getBloodType(),
                'group_uuid' => $before->getGroupUuid(),
            ],
            structAfter: [
                'user_uuid' => $updated->getUserUuid(),
                'first_name' => $updated->getFirstName(),
                'last_name_paternal' => $updated->getLastNamePaternal(),
                'last_name_maternal' => $updated->getLastNameMaternal(),
                'phone' => $updated->getPhone(),
                'birth_date' => $updated->getBirthDate(),
                'national_id' => $updated->getNationalId(),
                'enrollment_number' => $updated->getEnrollmentNumber(),
                'gender' => $updated->getGender(),
                'blood_type' => $updated->getBloodType(),
                'group_uuid' => $updated->getGroupUuid(),
            ],
        );

        return $updated;
    }
}
