<?php

use App\Common\Tenant\TenantContext;
use App\Models\Role as RoleModel;
use App\Models\School;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Modules\User\Domain\Criteria\UserListCriteria;
use App\Modules\User\Domain\Entities\User as UserEntity;
use App\Modules\User\Infrastructure\Repositories\EloquentUserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Helper: create a non-staff user in the given tenant.
 *
 * @param  array<string, mixed>  $attributes
 */
function userRepoCreateUser(Tenant $tenant, array $attributes = []): User
{
    return User::factory()->create(array_merge(
        ['tenant_id' => $tenant->id, 'is_staff' => false],
        $attributes
    ));
}

/**
 * Helper: assign a role to a user; returns the assignment model.
 */
function userRepoAssignRole(User $user, RoleModel $role, ?int $schoolId = null): UserRoleAssignment
{
    return UserRoleAssignment::factory()
        ->forUser($user)
        ->forRole($role)
        ->active()
        ->create(['school_id' => $schoolId]);
}

/**
 * Bind TenantContext in the service container for the given tenant.
 */
function userRepoBind(Tenant $tenant): void
{
    app()->instance(TenantContext::class, new TenantContext(
        tenantId: $tenant->id,
        ownerId: $tenant->owner_id,
    ));
}

describe('EloquentUserRepository', function () {
    beforeEach(function () {
        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        userRepoBind($this->tenantA);
        $this->repo = new EloquentUserRepository(app(TenantContext::class));
    });

    // -------------------------------------------------------------------------
    // Tenant isolation
    // -------------------------------------------------------------------------
    describe('tenant isolation', function () {
        it('only returns users belonging to the current tenant', function () {
            $mine = userRepoCreateUser($this->tenantA, ['email' => 'mine@test.com']);
            userRepoCreateUser($this->tenantB, ['email' => 'theirs@test.com']);

            $result = $this->repo->findAllPaginated(new UserListCriteria);

            $emails = array_map(fn (UserEntity $u) => $u->getEmail(), $result['items']);
            expect($emails)->toContain('mine@test.com');
            expect($emails)->not->toContain('theirs@test.com');
        });

        it('excludes is_staff = true users', function () {
            userRepoCreateUser($this->tenantA, ['email' => 'staff@test.com', 'is_staff' => true]);
            userRepoCreateUser($this->tenantA, ['email' => 'regular@test.com', 'is_staff' => false]);

            $result = $this->repo->findAllPaginated(new UserListCriteria);

            $emails = array_map(fn (UserEntity $u) => $u->getEmail(), $result['items']);
            expect($emails)->toContain('regular@test.com');
            expect($emails)->not->toContain('staff@test.com');
        });
    });

    // -------------------------------------------------------------------------
    // Search filter
    // -------------------------------------------------------------------------
    describe('search filter', function () {
        it('matches on first_name (case-insensitive)', function () {
            userRepoCreateUser($this->tenantA, ['first_name' => 'Alejandro', 'email' => 'alejandro@test.com']);
            userRepoCreateUser($this->tenantA, ['first_name' => 'Roberto', 'email' => 'roberto@test.com']);

            $result = $this->repo->findAllPaginated(new UserListCriteria(search: 'alejandro'));

            $emails = array_map(fn (UserEntity $u) => $u->getEmail(), $result['items']);
            expect($emails)->toContain('alejandro@test.com');
            expect($emails)->not->toContain('roberto@test.com');
        });

        it('matches on last_name_paternal (case-insensitive)', function () {
            userRepoCreateUser($this->tenantA, [
                'last_name_paternal' => 'García',
                'email' => 'garcia@test.com',
            ]);
            userRepoCreateUser($this->tenantA, [
                'last_name_paternal' => 'López',
                'email' => 'lopez@test.com',
            ]);

            $result = $this->repo->findAllPaginated(new UserListCriteria(search: 'garcía'));

            $emails = array_map(fn (UserEntity $u) => $u->getEmail(), $result['items']);
            expect($emails)->toContain('garcia@test.com');
            expect($emails)->not->toContain('lopez@test.com');
        });

        it('matches on last_name_maternal', function () {
            userRepoCreateUser($this->tenantA, [
                'last_name_maternal' => 'Mendoza',
                'email' => 'mendoza@test.com',
            ]);
            userRepoCreateUser($this->tenantA, [
                'last_name_maternal' => 'Ramos',
                'email' => 'ramos@test.com',
            ]);

            $result = $this->repo->findAllPaginated(new UserListCriteria(search: 'mendoza'));

            $emails = array_map(fn (UserEntity $u) => $u->getEmail(), $result['items']);
            expect($emails)->toContain('mendoza@test.com');
            expect($emails)->not->toContain('ramos@test.com');
        });

        it('matches on email', function () {
            userRepoCreateUser($this->tenantA, ['email' => 'matchable@example.com']);
            userRepoCreateUser($this->tenantA, ['email' => 'other@example.com']);

            $result = $this->repo->findAllPaginated(new UserListCriteria(search: 'matchable'));

            $emails = array_map(fn (UserEntity $u) => $u->getEmail(), $result['items']);
            expect($emails)->toContain('matchable@example.com');
            expect($emails)->not->toContain('other@example.com');
        });
    });

    // -------------------------------------------------------------------------
    // Status filter
    // -------------------------------------------------------------------------
    describe('status filter', function () {
        it('returns only users matching the given status', function () {
            userRepoCreateUser($this->tenantA, ['email' => 'active@test.com', 'status' => 'active']);
            userRepoCreateUser($this->tenantA, ['email' => 'inactive@test.com', 'status' => 'inactive']);

            $result = $this->repo->findAllPaginated(new UserListCriteria(status: 'inactive'));

            $emails = array_map(fn (UserEntity $u) => $u->getEmail(), $result['items']);
            expect($emails)->toContain('inactive@test.com');
            expect($emails)->not->toContain('active@test.com');
        });
    });

    // -------------------------------------------------------------------------
    // Role slug filter
    // -------------------------------------------------------------------------
    describe('roleSlugs filter', function () {
        it('returns only users with an active assignment to the given role slug', function () {
            $studentRole = RoleModel::factory()->forTenant($this->tenantA)->create(['slug' => 'student_filter_test']);
            $teacherRole = RoleModel::factory()->forTenant($this->tenantA)->create(['slug' => 'teacher_filter_test']);

            $student = userRepoCreateUser($this->tenantA, ['email' => 'student@test.com']);
            $teacher = userRepoCreateUser($this->tenantA, ['email' => 'teacher@test.com']);

            userRepoAssignRole($student, $studentRole);
            userRepoAssignRole($teacher, $teacherRole);

            $result = $this->repo->findAllPaginated(new UserListCriteria(roleSlugs: ['student_filter_test']));

            $emails = array_map(fn (UserEntity $u) => $u->getEmail(), $result['items']);
            expect($emails)->toContain('student@test.com');
            expect($emails)->not->toContain('teacher@test.com');
        });

        it('excludes users whose only matching assignment is revoked', function () {
            $role = RoleModel::factory()->forTenant($this->tenantA)->create(['slug' => 'revoked_role_test']);

            $activeUser = userRepoCreateUser($this->tenantA, ['email' => 'active_assigned@test.com']);
            $revokedUser = userRepoCreateUser($this->tenantA, ['email' => 'revoked_assigned@test.com']);

            userRepoAssignRole($activeUser, $role);

            // Revoked assignment — should NOT appear in role-filtered results
            UserRoleAssignment::factory()
                ->forUser($revokedUser)
                ->forRole($role)
                ->revoked()
                ->create();

            $result = $this->repo->findAllPaginated(new UserListCriteria(roleSlugs: ['revoked_role_test']));

            $emails = array_map(fn (UserEntity $u) => $u->getEmail(), $result['items']);
            expect($emails)->toContain('active_assigned@test.com');
            expect($emails)->not->toContain('revoked_assigned@test.com');
        });
    });

    // -------------------------------------------------------------------------
    // School filter
    // -------------------------------------------------------------------------
    describe('schoolIds filter', function () {
        it('returns only users with an active assignment in the given school', function () {
            $school = School::factory()->forTenant($this->tenantA)->create();
            $otherSchool = School::factory()->forTenant($this->tenantA)->create();
            $role = RoleModel::factory()->forTenant($this->tenantA)->create(['slug' => 'school_scoped_role']);

            $inSchool = userRepoCreateUser($this->tenantA, ['email' => 'in_school@test.com']);
            $otherSchoolUser = userRepoCreateUser($this->tenantA, ['email' => 'other_school@test.com']);
            $noSchoolUser = userRepoCreateUser($this->tenantA, ['email' => 'no_school@test.com']);

            userRepoAssignRole($inSchool, $role, $school->id);
            userRepoAssignRole($otherSchoolUser, $role, $otherSchool->id);
            // noSchoolUser has no assignment

            $result = $this->repo->findAllPaginated(new UserListCriteria(schoolIds: [$school->id]));

            $emails = array_map(fn (UserEntity $u) => $u->getEmail(), $result['items']);
            expect($emails)->toContain('in_school@test.com');
            expect($emails)->not->toContain('other_school@test.com');
            expect($emails)->not->toContain('no_school@test.com');
        });

        it('returns users across every school in the scope (whereIn)', function () {
            $schoolA = School::factory()->forTenant($this->tenantA)->create();
            $schoolB = School::factory()->forTenant($this->tenantA)->create();
            $schoolC = School::factory()->forTenant($this->tenantA)->create();
            $role = RoleModel::factory()->forTenant($this->tenantA)->create(['slug' => 'multi_school_role']);

            $userA = userRepoCreateUser($this->tenantA, ['email' => 'user_a@test.com']);
            $userB = userRepoCreateUser($this->tenantA, ['email' => 'user_b@test.com']);
            $userC = userRepoCreateUser($this->tenantA, ['email' => 'user_c@test.com']);

            userRepoAssignRole($userA, $role, $schoolA->id);
            userRepoAssignRole($userB, $role, $schoolB->id);
            userRepoAssignRole($userC, $role, $schoolC->id);

            // Scope = schools A and B only (e.g. a gestor managing those two).
            $result = $this->repo->findAllPaginated(new UserListCriteria(schoolIds: [$schoolA->id, $schoolB->id]));

            $emails = array_map(fn (UserEntity $u) => $u->getEmail(), $result['items']);
            expect($emails)->toContain('user_a@test.com');
            expect($emails)->toContain('user_b@test.com');
            expect($emails)->not->toContain('user_c@test.com');
        });

        it('returns no users when the school scope is an empty array', function () {
            $school = School::factory()->forTenant($this->tenantA)->create();
            $role = RoleModel::factory()->forTenant($this->tenantA)->create(['slug' => 'empty_scope_role']);

            $user = userRepoCreateUser($this->tenantA, ['email' => 'empty_scope@test.com']);
            userRepoAssignRole($user, $role, $school->id);

            // Empty scope = a non-owner actor with no accessible schools.
            $result = $this->repo->findAllPaginated(new UserListCriteria(schoolIds: []));

            expect($result['items'])->toBeEmpty();
            expect($result['total'])->toBe(0);
        });

        it('excludes tenant-level-only users when a school scope is set', function () {
            $school = School::factory()->forTenant($this->tenantA)->create();
            $tenantRole = RoleModel::factory()->forTenant($this->tenantA)->create(['slug' => 'tenant_level_only']);
            $schoolRole = RoleModel::factory()->forTenant($this->tenantA)->create(['slug' => 'school_level_only']);

            $tenantOnlyUser = userRepoCreateUser($this->tenantA, ['email' => 'tenant_only@test.com']);
            $schoolUser = userRepoCreateUser($this->tenantA, ['email' => 'school_user@test.com']);

            // Tenant-level assignment (school_id = null)
            userRepoAssignRole($tenantOnlyUser, $tenantRole, null);
            // School-level assignment
            userRepoAssignRole($schoolUser, $schoolRole, $school->id);

            $result = $this->repo->findAllPaginated(
                new UserListCriteria(schoolIds: [$school->id], roleSlugs: ['school_level_only'])
            );

            $emails = array_map(fn (UserEntity $u) => $u->getEmail(), $result['items']);
            expect($emails)->toContain('school_user@test.com');
            expect($emails)->not->toContain('tenant_only@test.com');
        });

        it('requires both schoolIds and roleSlugs to hold on the same assignment', function () {
            $school = School::factory()->forTenant($this->tenantA)->create();
            $roleA = RoleModel::factory()->forTenant($this->tenantA)->create(['slug' => 'role_a_combined']);
            $roleB = RoleModel::factory()->forTenant($this->tenantA)->create(['slug' => 'role_b_combined']);

            $userWithBothOnSame = userRepoCreateUser($this->tenantA, ['email' => 'both_same@test.com']);
            $userWithRoleAElsewhere = userRepoCreateUser($this->tenantA, ['email' => 'role_a_elsewhere@test.com']);

            // userWithBothOnSame: role_a in the target school
            userRepoAssignRole($userWithBothOnSame, $roleA, $school->id);

            // userWithRoleAElsewhere: role_a in a DIFFERENT school → does not satisfy schoolIds+roleSlug together
            $otherSchool = School::factory()->forTenant($this->tenantA)->create();
            userRepoAssignRole($userWithRoleAElsewhere, $roleA, $otherSchool->id);

            $result = $this->repo->findAllPaginated(new UserListCriteria(
                schoolIds: [$school->id],
                roleSlugs: ['role_a_combined'],
            ));

            $emails = array_map(fn (UserEntity $u) => $u->getEmail(), $result['items']);
            expect($emails)->toContain('both_same@test.com');
            expect($emails)->not->toContain('role_a_elsewhere@test.com');
        });
    });

    // -------------------------------------------------------------------------
    // Pagination
    // -------------------------------------------------------------------------
    describe('pagination', function () {
        it('returns the correct pagination shape', function () {
            userRepoCreateUser($this->tenantA, ['email' => 'page1@test.com']);
            userRepoCreateUser($this->tenantA, ['email' => 'page2@test.com']);

            $result = $this->repo->findAllPaginated(new UserListCriteria(perPage: 20, page: 1));

            expect($result)->toHaveKeys(['items', 'total', 'per_page', 'current_page', 'last_page']);
            expect($result['current_page'])->toBe(1);
        });

        it('respects perPage and page', function () {
            for ($i = 1; $i <= 5; $i++) {
                userRepoCreateUser($this->tenantA, ['email' => "pag{$i}@test.com"]);
            }

            $page1 = $this->repo->findAllPaginated(new UserListCriteria(perPage: 2, page: 1));
            $page2 = $this->repo->findAllPaginated(new UserListCriteria(perPage: 2, page: 2));

            // 5 total users created (+ owner from tenant factory — owner also in scope)
            expect($page1['per_page'])->toBe(2);
            expect($page1['current_page'])->toBe(1);
            expect($page2['current_page'])->toBe(2);
            expect(count($page1['items']))->toBe(2);
        });

        it('includes total count in the result', function () {
            userRepoCreateUser($this->tenantA, ['email' => 'count1@test.com']);
            userRepoCreateUser($this->tenantA, ['email' => 'count2@test.com']);
            userRepoCreateUser($this->tenantA, ['email' => 'count3@test.com']);

            $result = $this->repo->findAllPaginated(new UserListCriteria(perPage: 100));

            // 3 created + tenant owner (also a tenant user)
            expect($result['total'])->toBeGreaterThanOrEqual(3);
        });
    });

    // -------------------------------------------------------------------------
    // findByUuid
    // -------------------------------------------------------------------------
    describe('findByUuid', function () {
        it('returns the entity when the uuid belongs to the current tenant', function () {
            $user = userRepoCreateUser($this->tenantA, ['email' => 'uuid_lookup@test.com']);

            $result = $this->repo->findByUuid($user->uuid);

            expect($result)->not->toBeNull();
            expect($result->getUuid())->toBe($user->uuid);
            expect($result->getEmail())->toBe('uuid_lookup@test.com');
        });

        it('returns null when the uuid belongs to a different tenant', function () {
            $foreign = userRepoCreateUser($this->tenantB, ['email' => 'foreign@test.com']);

            $result = $this->repo->findByUuid($foreign->uuid);

            expect($result)->toBeNull();
        });

        it('returns null for a uuid that does not exist', function () {
            $result = $this->repo->findByUuid('00000000-0000-0000-0000-000000000000');

            expect($result)->toBeNull();
        });

        it('returns a UserEntity (not an Eloquent model)', function () {
            $user = userRepoCreateUser($this->tenantA);

            $result = $this->repo->findByUuid($user->uuid);

            expect($result)->toBeInstanceOf(UserEntity::class);
        });
    });

    // -------------------------------------------------------------------------
    // Roles on returned entities
    // -------------------------------------------------------------------------
    describe('roles populated on returned entities', function () {
        it('populates active role assignments with slug, name, and school_uuid', function () {
            $school = School::factory()->forTenant($this->tenantA)->create();
            $role = RoleModel::factory()->forTenant($this->tenantA)->create([
                'slug' => 'roles_populated_test',
                'name' => 'Roles Populated',
            ]);

            $user = userRepoCreateUser($this->tenantA, ['email' => 'roles_populated@test.com']);
            userRepoAssignRole($user, $role, $school->id);

            $entity = $this->repo->findByUuid($user->uuid);

            expect($entity)->not->toBeNull();
            expect($entity->getRoles())->toHaveCount(1);
            expect($entity->getRoles()[0]->slug)->toBe('roles_populated_test');
            expect($entity->getRoles()[0]->name)->toBe('Roles Populated');
            expect($entity->getRoles()[0]->schoolUuid)->toBe($school->uuid);
        });

        it('sets schoolUuid to null for tenant-level assignments (school_id IS NULL)', function () {
            $role = RoleModel::factory()->forTenant($this->tenantA)->create([
                'slug' => 'tenant_level_assignment',
                'name' => 'Tenant Level',
            ]);

            $user = userRepoCreateUser($this->tenantA, ['email' => 'tenant_level@test.com']);
            // Assign without a school
            userRepoAssignRole($user, $role, null);

            $entity = $this->repo->findByUuid($user->uuid);

            expect($entity)->not->toBeNull();
            expect($entity->getRoles())->toHaveCount(1);
            expect($entity->getRoles()[0]->schoolUuid)->toBeNull();
        });

        it('does not include revoked assignments in the roles collection', function () {
            $role = RoleModel::factory()->forTenant($this->tenantA)->create([
                'slug' => 'revoked_role_entity',
                'name' => 'Revoked',
            ]);

            $user = userRepoCreateUser($this->tenantA, ['email' => 'revoked_role@test.com']);

            // Create a revoked assignment
            UserRoleAssignment::factory()
                ->forUser($user)
                ->forRole($role)
                ->revoked()
                ->create();

            $entity = $this->repo->findByUuid($user->uuid);

            expect($entity)->not->toBeNull();
            expect($entity->getRoles())->toBeEmpty();
        });
    });
});
