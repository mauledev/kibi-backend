<?php

namespace App\Modules\User\Application\UseCases\InviteUser;

use App\Common\Mail\MailerInterface;
use App\Modules\Auth\Domain\Contracts\GlobalUserRepositoryInterface;
use App\Modules\Auth\Domain\Entities\User;
use App\Modules\Roles\Application\UseCases\AssignRoleToUser\AssignRoleToUserInput;
use App\Modules\Roles\Application\UseCases\AssignRoleToUser\AssignRoleToUserUseCase;
use App\Modules\Roles\Domain\Exceptions\HierarchyViolationException;
use App\Modules\Roles\Domain\Exceptions\OwnerRoleAssignmentException;
use App\Modules\Roles\Domain\Exceptions\RoleExclusionException;
use App\Modules\Roles\Domain\Exceptions\RoleNotFoundException;
use App\Modules\User\Domain\Exceptions\EmailAlreadyTakenException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Invite a tenant user: create a pending account, grant role/school assignments,
 * and email a signed activation (magic link).
 *
 * This is the member counterpart of CreateTenant's owner activation — it reuses
 * the exact same signed-route ('auth.activate') and mailer, so the invitee lands
 * on the same /auth/magic page, sets a password, and is logged in.
 *
 * Role assignments are delegated to AssignRoleToUserUseCase so hierarchy,
 * role-exclusion, and owner-role protections are enforced identically to the
 * standalone assignment endpoint.
 */
class InviteUserUseCase
{
    public function __construct(
        private readonly GlobalUserRepositoryInterface $users,
        private readonly AssignRoleToUserUseCase $assignRole,
        private readonly MailerInterface $mailer,
    ) {}

    /**
     * @throws EmailAlreadyTakenException When the email already exists.
     * @throws HierarchyViolationException
     * @throws RoleExclusionException
     * @throws RoleNotFoundException
     * @throws OwnerRoleAssignmentException
     */
    public function execute(InviteUserInput $input): User
    {
        if ($this->users->existsByEmail($input->email)) {
            throw new EmailAlreadyTakenException($input->email);
        }

        $user = DB::transaction(function () use ($input): User {
            $user = $this->users->createPending(
                email: $input->email,
                firstName: $input->firstName,
                lastNamePaternal: $input->lastNamePaternal,
                lastNameMaternal: $input->lastNameMaternal,
            );

            $this->users->setTenantId($user->getId(), $input->tenantId);

            foreach ($input->assignments as $assignment) {
                $this->assignRole->execute(new AssignRoleToUserInput(
                    actorUuid: $input->actorUuid,
                    actorSlug: $input->actorSlug,
                    targetUserUuid: $user->getUuid(),
                    roleUuid: $assignment['roleUuid'],
                    schoolUuid: $assignment['schoolUuid'] ?? null,
                ));
            }

            return $user;
        });

        $this->mailer->sendActivation(
            to: $input->email,
            activationUrl: $this->buildActivationUrl($user->getUuid(), $input->tenantSlug),
        );

        return $user;
    }

    /**
     * Build the tenant-aware frontend magic-link URL from a backend signed route.
     * Mirrors CreateTenantUseCase (same 'auth.activate' route, 7-day TTL).
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
        $baseUrl = rtrim(str_replace('{APP_TENANT}', $tenantSlug, $baseUrl), '/');

        $frontendUrl = $baseUrl.'/auth/magic';

        return $query ? "{$frontendUrl}?{$query}" : $frontendUrl;
    }
}
