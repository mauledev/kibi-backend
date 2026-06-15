<?php

namespace App\Modules\Student\Application\UseCases\CreateStudent;

use App\Common\Audit\AuditLoggerInterface;
use App\Models\User as UserModel;
use App\Modules\Auth\Domain\Contracts\GlobalUserRepositoryInterface;
use App\Modules\Roles\Application\UseCases\AssignRoleToUser\AssignRoleToUserInput;
use App\Modules\Roles\Application\UseCases\AssignRoleToUser\AssignRoleToUserUseCase;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Student\Domain\Contracts\StudentRepositoryInterface;
use App\Modules\Student\Domain\Entities\Student;
use App\Modules\User\Domain\Exceptions\EmailAlreadyTakenException;
use Illuminate\Support\Facades\DB;

/**
 * Create a student: set up a pending user account, assign the 'student' role
 * in the given school, create the student profile, and write an audit log entry.
 *
 * No password or activation email is sent — the Tutor manages the student's
 * credentials. The student account is left in 'pending' status.
 *
 * The 'student' role is resolved automatically by slug — the caller does not
 * supply a role UUID. If the role does not exist in the tenant, RoleNotFoundException
 * is thrown.
 */
final class CreateStudentUseCase
{
    public function __construct(
        private readonly GlobalUserRepositoryInterface $globalUsers,
        private readonly RoleRepositoryInterface $roles,
        private readonly AssignRoleToUserUseCase $assignRole,
        private readonly StudentRepositoryInterface $students,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Execute the use case.
     *
     * @throws EmailAlreadyTakenException When the email is already registered.
     * @throws RoleNotFoundException When the 'student' role does not exist in this tenant.
     */
    public function execute(CreateStudentInput $input): Student
    {
        if ($this->globalUsers->existsByEmail($input->email)) {
            throw new EmailAlreadyTakenException($input->email);
        }

        $studentRole = $this->roles->findBySlug('student');

        if ($studentRole === null) {
            throw new RoleNotFoundException('The student role does not exist in this tenant.');
        }

        $student = DB::transaction(function () use ($input, $studentRole): Student {
            $user = $this->globalUsers->createPending(
                email: $input->email,
                firstName: $input->firstName,
                lastNamePaternal: $input->lastNamePaternal,
                lastNameMaternal: $input->lastNameMaternal,
            );

            $this->globalUsers->setTenantId($user->getId(), $input->tenantId);

            // Update phone on the users row — GlobalUserRepositoryInterface does not
            // accept phone at creation time, so we update it directly here.
            if ($input->phone !== null) {
                UserModel::where('id', $user->getId())->update(['phone' => $input->phone]);
            }

            $this->assignRole->execute(new AssignRoleToUserInput(
                actorUuid: $input->actorUuid,
                actorSlug: $input->actorSlug,
                targetUserUuid: $user->getUuid(),
                roleUuid: $studentRole->getUuid(),
                schoolUuid: $input->schoolUuid,
            ));

            return $this->students->create(
                userUuid: $user->getUuid(),
                birthDate: $input->birthDate,
                nationalId: $input->nationalId,
                enrollmentNumber: $input->enrollmentNumber,
                gender: $input->gender,
                bloodType: $input->bloodType,
                groupId: $input->groupId,
            );
        });

        $this->audit->log(
            action: 'student.create',
            userId: $student->getUserId(),
            entityId: $student->getId(),
            structAfter: [
                'user_uuid' => $student->getUserUuid(),
                'email' => $student->getEmail(),
                'enrollment_number' => $student->getEnrollmentNumber(),
                'group_uuid' => $student->getGroupUuid(),
            ],
        );

        return $student;
    }
}
