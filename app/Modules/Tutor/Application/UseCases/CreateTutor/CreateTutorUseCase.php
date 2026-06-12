<?php

namespace App\Modules\Tutor\Application\UseCases\CreateTutor;

use App\Common\Audit\AuditLoggerInterface;
use App\Common\Mail\MailerInterface;
use App\Models\User as UserModel;
use App\Modules\Auth\Domain\Contracts\GlobalUserRepositoryInterface;
use App\Modules\Roles\Application\UseCases\AssignRoleToUser\AssignRoleToUserInput;
use App\Modules\Roles\Application\UseCases\AssignRoleToUser\AssignRoleToUserUseCase;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Tutor\Domain\Contracts\TutorRepositoryInterface;
use App\Modules\Tutor\Domain\Entities\Tutor;
use App\Modules\User\Domain\Exceptions\EmailAlreadyTakenException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Create a tutor: set up a pending user account, assign the 'tutor' role in
 * the given school, create the tutor profile, send a magic link, and write
 * an audit log entry.
 *
 * Unlike students, tutors DO receive an activation magic link at creation time
 * so they can set a password and access the portal to manage their linked students.
 *
 * The 'tutor' role is resolved automatically by slug — the caller does not
 * supply a role UUID. If the role does not exist in the tenant, RoleNotFoundException
 * is thrown.
 */
final class CreateTutorUseCase
{
    public function __construct(
        private readonly GlobalUserRepositoryInterface $globalUsers,
        private readonly RoleRepositoryInterface $roles,
        private readonly AssignRoleToUserUseCase $assignRole,
        private readonly TutorRepositoryInterface $tutors,
        private readonly MailerInterface $mailer,
        private readonly AuditLoggerInterface $audit,
    ) {}

    /**
     * Execute the use case.
     *
     * @throws EmailAlreadyTakenException When the email is already registered.
     * @throws RoleNotFoundException When the 'tutor' role does not exist in this tenant.
     */
    public function execute(CreateTutorInput $input): Tutor
    {
        if ($this->globalUsers->existsByEmail($input->email)) {
            throw new EmailAlreadyTakenException($input->email);
        }

        $tutorRole = $this->roles->findBySlug('tutor');

        if ($tutorRole === null) {
            throw new RoleNotFoundException('The tutor role does not exist in this tenant.');
        }

        $tutor = DB::transaction(function () use ($input, $tutorRole): Tutor {
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
                roleUuid: $tutorRole->getUuid(),
                schoolUuid: $input->schoolUuid,
            ));

            return $this->tutors->create(
                userUuid: $user->getUuid(),
                occupation: $input->occupation,
            );
        });

        $this->mailer->sendActivation(
            to: $input->email,
            activationUrl: $this->buildActivationUrl($tutor->getUserUuid(), $input->tenantSlug),
        );

        $this->audit->log(
            action: 'tutor.create',
            userId: $tutor->getUserId(),
            entityId: $tutor->getId(),
            structAfter: [
                'user_uuid' => $tutor->getUserUuid(),
                'email' => $tutor->getEmail(),
                'occupation' => $tutor->getOccupation(),
            ],
        );

        return $tutor;
    }

    /**
     * Build the tenant-aware frontend magic-link URL from a backend signed route.
     * Mirrors InviteUserUseCase (same 'auth.activate' route, 7-day TTL).
     */
    private function buildActivationUrl(string $userUuid, string $tenantSlug): string
    {
        $backendSignedUrl = URL::temporarySignedRoute(
            'auth.activate',
            now()->addHours(168),
            ['user' => $userUuid],
            absolute: false,
        );

        $query = parse_url($backendSignedUrl, PHP_URL_QUERY);

        $baseUrl = config('app.frontend_url') ?? config('app.url');
        $baseUrl = rtrim(str_replace('{APP_TENANT}', $tenantSlug, (string) $baseUrl), '/');

        $frontendUrl = $baseUrl.'/auth/magic';

        return $query ? "{$frontendUrl}?{$query}" : $frontendUrl;
    }
}
