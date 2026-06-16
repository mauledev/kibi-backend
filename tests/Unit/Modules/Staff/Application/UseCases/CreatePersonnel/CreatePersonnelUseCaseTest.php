<?php

use Tests\TestCase;

uses(TestCase::class);

use App\Common\Mail\MailerInterface;
use App\Modules\Auth\Domain\Contracts\GlobalUserRepositoryInterface;
use App\Modules\Auth\Domain\Entities\User as AuthUser;
use App\Modules\Roles\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Roles\Domain\Contracts\UserRoleAssignmentRepositoryInterface;
use App\Modules\Roles\Domain\Entities\Permission;
use App\Modules\Roles\Domain\Entities\Role;
use App\Modules\Roles\Domain\Entities\UserRoleAssignment;
use App\Modules\Staff\Application\UseCases\CreatePersonnel\CreatePersonnelInput;
use App\Modules\Staff\Application\UseCases\CreatePersonnel\CreatePersonnelUseCase;
use App\Modules\Staff\Domain\Contracts\StaffWorkScheduleRepositoryInterface;
use App\Modules\Staff\Domain\Entities\WorkSchedule;
use App\Modules\Staff\Domain\Exceptions\InvalidStaffRoleException;
use App\Modules\Staff\Domain\Exceptions\PermissionNotAllowedException;
use App\Modules\Staff\Domain\Exceptions\StaffEmailAlreadyTakenException;
use App\Modules\Staff\Domain\Exceptions\StaffRoleNotFoundException;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\DB;

/**
 * @param  array<string>  $permissionSlugs
 */
function staffRole(string $slug, array $permissionSlugs): Role
{
    $permissions = [];
    $id = 1;
    foreach ($permissionSlugs as $permSlug) {
        $permissions[] = new Permission(
            id: $id,
            uuid: "perm-uuid-{$id}",
            categoryId: 1,
            name: $permSlug,
            slug: $permSlug,
            createdAt: new DateTimeImmutable,
        );
        $id++;
    }

    return new Role(
        id: 10,
        uuid: 'role-uuid',
        tenantId: null,
        categoryId: 1,
        name: ucfirst($slug),
        slug: $slug,
        hierarchyLevel: 3,
        isSystemRole: true,
        permissions: $permissions,
        requiresTwoFactor: in_array($slug, ['leader', 'support'], true),
    );
}

function staffAuthUser(): AuthUser
{
    return new AuthUser(
        id: 100,
        uuid: 'user-uuid-100',
        email: 'new@softlinkia.com',
        firstName: 'New',
        lastNamePaternal: 'Staff',
        lastNameMaternal: null,
        passwordHash: null,
        status: 'active',
        createdAt: new DateTime,
        isStaff: true,
        tenantId: null,
    );
}

function staffAssignment(): UserRoleAssignment
{
    return new UserRoleAssignment(
        id: 500,
        uuid: 'assignment-uuid',
        userId: 100,
        roleId: 10,
        schoolId: null,
        assignedBy: 1,
        assignedAt: new DateTimeImmutable,
    );
}

/**
 * @param  array<string>  $permissions
 */
function staffInput(string $role = 'operator', array $permissions = ['billing.view', 'billing.approve', 'remittance.create']): CreatePersonnelInput
{
    return new CreatePersonnelInput(
        role: $role,
        firstName: 'New',
        lastNamePaternal: 'Staff',
        lastNameMaternal: null,
        email: 'new@softlinkia.com',
        phone: null,
        workSchedule: new WorkSchedule('America/Mexico_City', ['mon'], '09:00', '18:00'),
        permissions: $permissions,
        createdBy: 1,
    );
}

describe('CreatePersonnelUseCase', function () {
    beforeEach(function () {
        $this->users = Mockery::mock(GlobalUserRepositoryInterface::class);
        $this->roles = Mockery::mock(RoleRepositoryInterface::class);
        $this->assignments = Mockery::mock(UserRoleAssignmentRepositoryInterface::class);
        $this->workSchedules = Mockery::mock(StaffWorkScheduleRepositoryInterface::class);
        $this->mailer = Mockery::mock(MailerInterface::class);

        $this->useCase = new CreatePersonnelUseCase(
            $this->users,
            $this->roles,
            $this->assignments,
            $this->workSchedules,
            $this->mailer,
        );

        // Run the transaction closure inline — no real DB in this unit test.
        DB::shouldReceive('transaction')->andReturnUsing(fn ($callback) => $callback());
    });

    afterEach(function () {
        Mockery::close();
    });

    /**
     * The activation email is dispatched via defer() (runs after the HTTP response
     * in production). Flush it synchronously so unit-test assertions on the mailer
     * mock are satisfied. Must run inside the test body (the scoped collection is
     * reset on framework tearDown, before afterEach).
     */
    function flushDeferred(): void
    {
        app(DeferredCallbackCollection::class)->invoke();
    }

    it('creates the user, assigns the role, persists the schedule and emails activation', function () {
        $this->users->shouldReceive('existsByEmail')->once()->andReturn(false);
        $this->roles->shouldReceive('findBySlug')->with('operator')->once()
            ->andReturn(staffRole('operator', ['billing.view', 'billing.approve', 'remittance.create']));
        $this->users->shouldReceive('createPendingStaff')->once()->andReturn(staffAuthUser());
        $this->assignments->shouldReceive('create')->once()->andReturn(staffAssignment());
        $this->assignments->shouldReceive('addDenial')->never();
        $this->workSchedules->shouldReceive('create')->once();
        $this->mailer->shouldReceive('sendActivation')->once();

        $result = $this->useCase->execute(staffInput());
        flushDeferred();

        expect($result->getRole())->toBe('operator');
        expect($result->requires2fa())->toBeFalse();
        expect($result->getPermissions())->toBe(['billing.view', 'billing.approve', 'remittance.create']);
        expect($result->getWorkSchedule()->getTimezone())->toBe('America/Mexico_City');
    });

    it('denies unchecked permissions and keeps only the selected subset', function () {
        $this->users->shouldReceive('existsByEmail')->andReturn(false);
        $this->roles->shouldReceive('findBySlug')
            ->andReturn(staffRole('operator', ['billing.view', 'billing.approve', 'remittance.create']));
        $this->users->shouldReceive('createPendingStaff')->andReturn(staffAuthUser());
        $this->assignments->shouldReceive('create')->andReturn(staffAssignment());
        $this->assignments->shouldReceive('addDenial')->twice(); // billing.approve + remittance.create
        $this->workSchedules->shouldReceive('create')->once();
        $this->mailer->shouldReceive('sendActivation')->once();

        $result = $this->useCase->execute(staffInput('operator', ['billing.view']));
        flushDeferred();

        expect($result->getPermissions())->toBe(['billing.view']);
    });

    it('derives requires_2fa from the role (leader requires 2FA)', function () {
        $this->users->shouldReceive('existsByEmail')->andReturn(false);
        $this->roles->shouldReceive('findBySlug')->andReturn(staffRole('leader', ['billing.view']));
        $this->users->shouldReceive('createPendingStaff')->andReturn(staffAuthUser());
        $this->assignments->shouldReceive('create')->andReturn(staffAssignment());
        $this->workSchedules->shouldReceive('create');
        $this->mailer->shouldReceive('sendActivation');

        $result = $this->useCase->execute(staffInput('leader', ['billing.view']));

        expect($result->requires2fa())->toBeTrue();
    });

    it('throws InvalidStaffRoleException for an unknown role', function () {
        $this->users->shouldReceive('existsByEmail')->never();

        expect(fn () => $this->useCase->execute(staffInput('ghost')))
            ->toThrow(InvalidStaffRoleException::class);
    });

    it('throws StaffEmailAlreadyTakenException when the email exists', function () {
        $this->users->shouldReceive('existsByEmail')->once()->andReturn(true);
        $this->roles->shouldReceive('findBySlug')->never();

        expect(fn () => $this->useCase->execute(staffInput()))
            ->toThrow(StaffEmailAlreadyTakenException::class);
    });

    it('throws StaffRoleNotFoundException when the role is not seeded', function () {
        $this->users->shouldReceive('existsByEmail')->andReturn(false);
        $this->roles->shouldReceive('findBySlug')->once()->andReturn(null);

        expect(fn () => $this->useCase->execute(staffInput()))
            ->toThrow(StaffRoleNotFoundException::class);
    });

    it('throws PermissionNotAllowedException for a permission outside the catalogue', function () {
        $this->users->shouldReceive('existsByEmail')->andReturn(false);
        $this->roles->shouldReceive('findBySlug')->andReturn(staffRole('operator', ['billing.view']));
        $this->users->shouldReceive('createPendingStaff')->never();

        expect(fn () => $this->useCase->execute(staffInput('operator', ['totally.invalid'])))
            ->toThrow(PermissionNotAllowedException::class);
    });
});
