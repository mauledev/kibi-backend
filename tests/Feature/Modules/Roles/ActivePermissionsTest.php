<?php

use App\Common\School\SchoolContext;
use App\Common\Tenant\TenantContext;
use App\Models\Permission as PermissionModel;
use App\Models\PermissionCategory;
use App\Models\Role as RoleModel;
use App\Models\School;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Models\UserRoleAssignmentDenial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

function apAssignRole(User $user, RoleModel $role, ?int $schoolId = null): UserRoleAssignment
{
    return UserRoleAssignment::factory()
        ->forUser($user)
        ->forRole($role)
        ->active()
        ->create(['school_id' => $schoolId]);
}

function apGrantPermission(RoleModel $role, string $slug): PermissionModel
{
    $category = PermissionCategory::factory()->system()->create();
    $permission = PermissionModel::factory()->withSlug($slug)->create(['category_id' => $category->id]);
    $role->permissions()->attach($permission->id);

    return $permission;
}

describe('User::activePermissions($schoolId)', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        $this->school = School::factory()->forTenant($this->tenant)->create();
        $this->otherSchool = School::factory()->forTenant($this->tenant)->create();
    });

    it('returns role permissions minus denials for the given school', function () {
        $user = User::factory()->create();
        $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'director_ap']);

        $perm1 = apGrantPermission($role, 'grade.view');
        $perm2 = apGrantPermission($role, 'grade.delete');

        $assignment = apAssignRole($user, $role, $this->school->id);

        // Deny grade.delete on this assignment
        UserRoleAssignmentDenial::create([
            'role_user_assignment_id' => $assignment->id,
            'permission_id' => $perm2->id,
        ]);

        $user->refresh();

        $permissions = $user->activePermissions($this->school->id);

        expect($permissions)->toContain('grade.view');
        expect($permissions)->not->toContain('grade.delete');
    });

    it('does not include permissions denied in another school assignment', function () {
        $user = User::factory()->create();
        $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'director_ap2']);

        $perm = apGrantPermission($role, 'invoice.delete');

        $assignmentSchoolA = apAssignRole($user, $role, $this->school->id);
        $assignmentSchoolB = apAssignRole($user, $role, $this->otherSchool->id);

        // Deny invoice.delete only in school B
        UserRoleAssignmentDenial::create([
            'role_user_assignment_id' => $assignmentSchoolB->id,
            'permission_id' => $perm->id,
        ]);

        $user->refresh();

        // School A has no denial — permission should be present
        $schoolAPermissions = $user->activePermissions($this->school->id);
        expect($schoolAPermissions)->toContain('invoice.delete');

        // School B has the denial — permission should be absent
        $user->refresh(); // reset memoized cache
        $schoolBPermissions = $user->activePermissions($this->otherSchool->id);
        expect($schoolBPermissions)->not->toContain('invoice.delete');
    });

    it('includes permissions from tenant-level assignments (school_id IS NULL) when checking school context', function () {
        $user = User::factory()->create();
        $tenantRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_ap']);

        apGrantPermission($tenantRole, 'user.create');

        // Tenant-level assignment: school_id IS NULL
        apAssignRole($user, $tenantRole, null);

        $user->refresh();

        // Even though checking school context, tenant-level assignments are included
        $permissions = $user->activePermissions($this->school->id);
        expect($permissions)->toContain('user.create');
    });

    it('returns empty array when user has no assignments in that school', function () {
        $user = User::factory()->create();
        // No assignments at all

        $permissions = $user->activePermissions($this->school->id);

        expect($permissions)->toBeArray()->toBeEmpty();
    });

    it('returns empty array when user has assignments only in a different school', function () {
        $user = User::factory()->create();
        $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'director_ap3']);

        apGrantPermission($role, 'grade.publish');
        apAssignRole($user, $role, $this->otherSchool->id);

        $user->refresh();

        // Querying school — user has no assignment there
        $permissions = $user->activePermissions($this->school->id);
        expect($permissions)->toBeArray()->toBeEmpty();
    });
});

describe('Gestor Gate bypass', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        $this->school = School::factory()->forTenant($this->tenant)->create();
        $this->otherSchool = School::factory()->forTenant($this->tenant)->create();

        app()->instance(TenantContext::class, new TenantContext(
            tenantId: $this->tenant->id,
            ownerId: $this->tenant->owner_id,
        ));
    });

    it('gestor passes Gate check for any permission in their assigned school', function () {
        $gestor = User::factory()->create();
        $gestorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'school_manager']);
        apAssignRole($gestor, $gestorRole, $this->school->id);

        $gestor->refresh();

        // Bind school context for the gate check
        app()->instance(SchoolContext::class, new SchoolContext(
            schoolId: $this->school->id,
        ));

        $this->assertTrue(Gate::forUser($gestor)->allows('any.permission.slug'));
    });

    it('gestor does NOT pass Gate check in a school they are NOT assigned to', function () {
        $gestor = User::factory()->create();
        $gestorRole = RoleModel::factory()->forTenant($this->tenant)->atLevel(3)->create(['slug' => 'gestor_gate2']);
        apAssignRole($gestor, $gestorRole, $this->school->id);

        $gestor->refresh();

        // Check against the OTHER school where gestor has no assignment
        app()->instance(SchoolContext::class, new SchoolContext(
            schoolId: $this->otherSchool->id,
        ));

        $this->assertFalse(Gate::forUser($gestor)->allows('any.permission.slug'));
    });
});
