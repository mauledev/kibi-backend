<?php

namespace App\Modules\Tenant\Application\UseCases\CreateTenant;

use App\Common\Mail\MailerInterface;
use App\Modules\Auth\Domain\Contracts\GlobalUserRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\UserRoleAssignmentRepositoryInterface;
use App\Modules\Tenant\Domain\Contracts\TenantRepositoryInterface;
use App\Modules\Tenant\Domain\Entities\Tenant;
use App\Modules\Tenant\Domain\Exceptions\EmailAlreadyTakenException;
use App\Modules\Tenant\Domain\Exceptions\TenantSlugAlreadyExistsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

use function Illuminate\Support\defer;

class CreateTenantUseCase
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
        private readonly GlobalUserRepositoryInterface $users,
        private readonly UserRoleAssignmentRepositoryInterface $assignments,
        private readonly MailerInterface $mailer,
    ) {}

    /**
     * Create a new tenant with its owner user and send an activation email.
     *
     * Business rules enforced:
     * - tenant_slug must not already exist.
     * - owner_email must not already exist anywhere in the users table.
     * - Owner user is created with password_hash = null and email_verified_at = null.
     * - Tenant is created with status = 'pending'.
     * - Owner user's tenant_id is set within the same transaction.
     * - The 'owner' role is first-or-created and assigned to the owner user.
     * - A signed activation URL (48 h TTL) is generated and emailed to the owner.
     *
     * @throws TenantSlugAlreadyExistsException When the slug is already taken.
     * @throws EmailAlreadyTakenException When the email is already registered.
     */
    public function execute(CreateTenantInput $input): Tenant
    {
        if ($this->tenants->findBySlug($input->tenantSlug) !== null) {
            throw new TenantSlugAlreadyExistsException($input->tenantSlug);
        }

        if ($this->users->existsByEmail($input->ownerEmail)) {
            throw new EmailAlreadyTakenException($input->ownerEmail);
        }

        $tenant = DB::transaction(function () use ($input): Tenant {
            $owner = $this->users->createPending(
                email: $input->ownerEmail,
                firstName: $input->ownerFirstName,
                lastNamePaternal: $input->ownerLastNamePaternal,
                lastNameMaternal: $input->ownerLastNameMaternal,
            );

            $tenant = $this->tenants->create(
                name: $input->tenantName,
                slug: $input->tenantSlug,
                ownerId: $owner->getId(),
            );

            $this->users->setTenantId($owner->getId(), $tenant->getId());

            $this->assignments->createOwnerAssignment(
                userId: $owner->getId(),
            );

            return $this->tenants->findBySlugWithOwner($input->tenantSlug);
        });

        $backendSignedUrl = URL::temporarySignedRoute(
            'auth.activate',
            now()->addHours(168),
            ['user' => $tenant->getOwner()?->getUuid()],
            absolute: false
        );

        $query = parse_url($backendSignedUrl, PHP_URL_QUERY);

        $baseUrl = config('app.frontend_url') ?? config('app.url');

        $baseUrlWithTenant = str_replace('{APP_TENANT}', $input->tenantSlug, $baseUrl);
        $baseUrlWithTenant = rtrim($baseUrlWithTenant, '/');

        $frontendUrl = $baseUrlWithTenant.'/auth/magic';
        $activationUrl = $query ? "{$frontendUrl}?{$query}" : $frontendUrl;

        // Send the activation email after the response is flushed (see CreatePersonnel).
        defer(fn () => $this->mailer->sendActivation(
            to: $input->ownerEmail,
            activationUrl: $activationUrl,
        ));

        return $tenant;
    }
}
