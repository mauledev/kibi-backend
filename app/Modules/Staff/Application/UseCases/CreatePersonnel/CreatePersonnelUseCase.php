<?php

namespace App\Modules\Staff\Application\UseCases\CreatePersonnel;

use App\Common\Mail\MailerInterface;
use App\Modules\Auth\Domain\Contracts\GlobalUserRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\UserRoleAssignmentRepositoryInterface;
use App\Modules\Staff\Domain\Contracts\StaffWorkScheduleRepositoryInterface;
use App\Modules\Staff\Domain\Entities\StaffMember;
use App\Modules\Staff\Domain\Enums\StaffRoleEnum;
use App\Modules\Staff\Domain\Exceptions\InvalidStaffRoleException;
use App\Modules\Staff\Domain\Exceptions\PermissionNotAllowedException;
use App\Modules\Staff\Domain\Exceptions\StaffEmailAlreadyTakenException;
use App\Modules\Staff\Domain\Exceptions\StaffRoleNotFoundException;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

use function Illuminate\Support\defer;

/**
 * Create a Softlinkia Backoffice staff member (operator / leader / support).
 *
 * Mirrors the CreateTenant pattern: validate input, then persist the user and
 * its role assignment in a single transaction, and finally email the staff
 * member a signed activation (magic) link. Permissions follow Path B
 * (role_permissions): the member inherits the role's default permissions, and
 * any permission the actor unchecked is recorded as a per-assignment denial.
 *
 * Out of scope (separate stories): 2FA enrollment, work schedule persistence,
 * audit log.
 *
 * @throws InvalidStaffRoleException When the role slug is not a staff role.
 * @throws StaffEmailAlreadyTakenException When the email already exists.
 * @throws StaffRoleNotFoundException When the role is not seeded.
 * @throws PermissionNotAllowedException When a permission is outside the role catalogue.
 */
class CreatePersonnelUseCase
{
    public function __construct(
        private readonly GlobalUserRepositoryInterface $users,
        private readonly RoleRepositoryInterface $roles,
        private readonly UserRoleAssignmentRepositoryInterface $assignments,
        private readonly StaffWorkScheduleRepositoryInterface $workSchedules,
        private readonly MailerInterface $mailer,
    ) {}

    public function execute(CreatePersonnelInput $input): StaffMember
    {
        $role = StaffRoleEnum::tryFrom($input->role);

        if ($role === null) {
            throw new InvalidStaffRoleException($input->role);
        }

        if ($this->users->existsByEmail($input->email)) {
            throw new StaffEmailAlreadyTakenException($input->email);
        }

        $roleEntity = $this->roles->findBySlug($role->value);

        if ($roleEntity === null) {
            throw new StaffRoleNotFoundException($role->value);
        }

        $defaultPermissions = $roleEntity->getPermissions();
        $defaultSlugs = array_map(fn ($permission) => $permission->getSlug(), $defaultPermissions);

        // Creation can only narrow a role: every requested permission must be a default.
        foreach ($input->permissions as $slug) {
            if (! in_array($slug, $defaultSlugs, true)) {
                throw new PermissionNotAllowedException($slug, $role->value);
            }
        }

        $member = DB::transaction(function () use ($input, $role, $roleEntity, $defaultPermissions): StaffMember {
            $user = $this->users->createPendingStaff(
                email: $input->email,
                firstName: $input->firstName,
                lastNamePaternal: $input->lastNamePaternal,
                lastNameMaternal: $input->lastNameMaternal,
                phone: $input->phone,
            );

            $assignment = $this->assignments->create(
                userId: $user->getId(),
                roleId: $roleEntity->getId(),
                schoolId: null,
                assignedBy: $input->createdBy,
            );

            // Deny every default permission the actor unchecked; keep the rest as effective.
            $effective = [];
            foreach ($defaultPermissions as $permission) {
                if (in_array($permission->getSlug(), $input->permissions, true)) {
                    $effective[] = $permission->getSlug();
                } else {
                    $this->assignments->addDenial($assignment->getId(), $permission->getId());
                }
            }

            $this->workSchedules->create($user->getId(), $input->workSchedule);

            return new StaffMember(
                uuid: $user->getUuid(),
                role: $role->value,
                firstName: $input->firstName,
                lastNamePaternal: $input->lastNamePaternal,
                lastNameMaternal: $input->lastNameMaternal,
                email: $input->email,
                phone: $input->phone,
                workSchedule: $input->workSchedule,
                permissions: $effective,
                requires2fa: $roleEntity->requiresTwoFactor(),
                createdAt: DateTimeImmutable::createFromInterface($user->getCreatedAt()),
            );
        });

        // Send the activation email AFTER the HTTP response is flushed to the client.
        // Keeps creation fast (cold Blade compile / SMTP handshake no longer blocks the
        // request) and prevents the 15s client timeout that caused duplicate-on-retry.
        defer(fn () => $this->sendActivationEmail($member->getUuid(), $member->getEmail()));

        return $member;
    }

    /**
     * Email the staff member a signed activation (magic) link.
     *
     * Same mechanism as tenant onboarding (CreateTenant), but the link points to
     * the staff frontend host ({APP_TENANT} → "staff", e.g. staff.kibi.com) since
     * staff users have no tenant slug. The link hits the shared, tenant-agnostic
     * POST /auth/activate endpoint.
     */
    private function sendActivationEmail(string $userUuid, string $email): void
    {
        $signedUrl = URL::temporarySignedRoute(
            'auth.activate',
            now()->addHours(168),
            ['user' => $userUuid],
            absolute: false,
        );

        $query = parse_url($signedUrl, PHP_URL_QUERY);

        $baseUrl = config('app.frontend_url') ?? config('app.url');
        $baseUrl = str_replace('{APP_TENANT}', 'staff', $baseUrl);
        $baseUrl = rtrim($baseUrl, '/');

        $frontendUrl = $baseUrl.'/auth/magic';
        $activationUrl = $query ? "{$frontendUrl}?{$query}" : $frontendUrl;

        $this->mailer->sendActivation(to: $email, activationUrl: $activationUrl);
    }
}
