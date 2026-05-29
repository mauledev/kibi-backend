<?php

use App\Models\Role as RoleModel;
use App\Models\School;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment as AssignmentModel;
use App\Modules\Roles\Domain\Entities\UserRoleAssignment;
use App\Modules\Roles\Infrastructure\Repositories\EloquentUserRoleAssignmentRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

describe('EloquentUserRoleAssignmentRepository', function () {
    beforeEach(function () {
        $this->repo = new EloquentUserRoleAssignmentRepository;
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create();
        $this->role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create(['slug' => 'test_role']);
    });

    describe('findActiveByUserId', function () {
        it('returns active assignments for a user', function () {
            AssignmentModel::factory()->forUser($this->user)->forRole($this->role)->active()->create();

            $results = $this->repo->findActiveByUserId($this->user->id);

            expect($results)->toHaveCount(1);
            expect($results[0])->toBeInstanceOf(UserRoleAssignment::class);
            expect($results[0]->getUserId())->toBe($this->user->id);
        });

        it('does not return revoked assignments', function () {
            AssignmentModel::factory()->forUser($this->user)->forRole($this->role)->revoked()->create();

            $results = $this->repo->findActiveByUserId($this->user->id);

            expect($results)->toBeEmpty();
        });

        it('returns empty array when user has no active assignments', function () {
            $results = $this->repo->findActiveByUserId(99999);

            expect($results)->toBeArray()->toBeEmpty();
        });

        it('returns multiple active assignments for a user with multiple roles', function () {
            $roleB = RoleModel::factory()->forTenant($this->tenant)->atLevel(6)->create(['slug' => 'role_b_assign']);
            AssignmentModel::factory()->forUser($this->user)->forRole($this->role)->active()->create();
            AssignmentModel::factory()->forUser($this->user)->forRole($roleB)->active()->create();

            $results = $this->repo->findActiveByUserId($this->user->id);

            expect($results)->toHaveCount(2);
        });
    });

    describe('findActiveByUserAndRole', function () {
        it('returns active assignment for a user and role without school', function () {
            AssignmentModel::factory()->forUser($this->user)->forRole($this->role)->active()->create(['school_id' => null]);

            $result = $this->repo->findActiveByUserAndRole($this->user->id, $this->role->id, null);

            expect($result)->not->toBeNull();
            expect($result->getRoleId())->toBe($this->role->id);
        });

        it('returns null when assignment is revoked', function () {
            AssignmentModel::factory()->forUser($this->user)->forRole($this->role)->revoked()->create(['school_id' => null]);

            $result = $this->repo->findActiveByUserAndRole($this->user->id, $this->role->id, null);

            expect($result)->toBeNull();
        });

        it('returns null when no assignment exists for this user and role', function () {
            $result = $this->repo->findActiveByUserAndRole($this->user->id, $this->role->id, null);

            expect($result)->toBeNull();
        });

        it('does not match school-scoped assignment when looking for tenant-level', function () {
            $school = School::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'name' => 'Test School',
                'slug' => 'test-school',
                'status' => 'active',
            ]);

            AssignmentModel::factory()->forUser($this->user)->forRole($this->role)->active()->create(['school_id' => $school->id]);

            $result = $this->repo->findActiveByUserAndRole($this->user->id, $this->role->id, null);

            expect($result)->toBeNull();
        });
    });

    describe('create', function () {
        it('persists a new assignment and returns the domain entity', function () {
            $result = $this->repo->create(
                userId: $this->user->id,
                roleId: $this->role->id,
                schoolId: null,
                assignedBy: null,
            );

            expect($result)->toBeInstanceOf(UserRoleAssignment::class);
            expect($result->getUserId())->toBe($this->user->id);
            expect($result->getRoleId())->toBe($this->role->id);
            expect($result->getSchoolId())->toBeNull();
            expect($result->isActive())->toBeTrue();

            $this->assertDatabaseHas('user_role_assignments', [
                'user_id' => $this->user->id,
                'role_id' => $this->role->id,
                'revoked_at' => null,
            ]);
        });

        it('stores the assignedBy user id when provided', function () {
            $assigner = User::factory()->create();

            $result = $this->repo->create(
                userId: $this->user->id,
                roleId: $this->role->id,
                schoolId: null,
                assignedBy: $assigner->id,
            );

            expect($result->getAssignedBy())->toBe($assigner->id);
        });
    });

    describe('revoke', function () {
        it('sets revoked_at on the assignment', function () {
            $assignment = AssignmentModel::factory()->forUser($this->user)->forRole($this->role)->active()->create();

            $result = $this->repo->revoke($assignment->id);

            expect($result)->toBeInstanceOf(UserRoleAssignment::class);
            expect($result->isActive())->toBeFalse();
            expect($result->getRevokedAt())->not->toBeNull();

            $this->assertDatabaseHas('user_role_assignments', [
                'id' => $assignment->id,
            ]);

            $refreshed = AssignmentModel::find($assignment->id);
            expect($refreshed->revoked_at)->not->toBeNull();
        });

        it('throws when assignment id does not exist', function () {
            expect(fn () => $this->repo->revoke(99999))
                ->toThrow(ModelNotFoundException::class);
        });
    });

    describe('idempotency — assignment already active', function () {
        it('findActiveByUserAndRole returns the existing assignment when already active', function () {
            AssignmentModel::factory()->forUser($this->user)->forRole($this->role)->active()->create(['school_id' => null]);
            AssignmentModel::factory()->forUser($this->user)->forRole($this->role)->active()->create(['school_id' => null]);

            // Both exist — repo returns the first found
            $result = $this->repo->findActiveByUserAndRole($this->user->id, $this->role->id, null);

            expect($result)->not->toBeNull();
        });
    });
});
