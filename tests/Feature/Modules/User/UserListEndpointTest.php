<?php

use App\Models\Permission as PermissionModel;
use App\Models\PermissionCategory;
use App\Models\Role as RoleModel;
use App\Models\School;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Create a user scoped to the given tenant.
 *
 * @param  array<string, mixed>  $attributes
 */
function userEndpointCreateTenantUser(Tenant $tenant, array $attributes = []): User
{
    return User::factory()->create(array_merge(
        ['tenant_id' => $tenant->id, 'is_staff' => false],
        $attributes
    ));
}

/**
 * Assign a role to a user and return the assignment.
 */
function userEndpointAssignRole(User $user, RoleModel $role, ?int $schoolId = null): UserRoleAssignment
{
    return UserRoleAssignment::factory()
        ->forUser($user)
        ->forRole($role)
        ->active()
        ->create(['school_id' => $schoolId]);
}

/**
 * Attach a permission with the given slug to the given role.
 */
function userEndpointGrantPermission(RoleModel $role, string $slug): PermissionModel
{
    $category = PermissionCategory::factory()->system()->create();
    $permission = PermissionModel::factory()->withSlug($slug)->create(['category_id' => $category->id]);
    $role->permissions()->attach($permission->id);

    return $permission;
}

describe('User endpoints', function () {
    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        // The tenant owner bypasses all Gate::before checks.
        $this->owner = User::find($this->tenant->owner_id);
    });

    // -------------------------------------------------------------------------
    // Authentication and authorization
    // -------------------------------------------------------------------------
    describe('authentication and authorization', function () {
        it('GET /users returns 401 when unauthenticated', function () {
            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/users')
                ->assertStatus(401);
        });

        it('GET /users/{uuid} returns 401 when unauthenticated', function () {
            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/users/00000000-0000-0000-0000-000000000000')
                ->assertStatus(401);
        });

        it('GET /users returns 403 when authenticated user lacks user.view permission', function () {
            $user = userEndpointCreateTenantUser($this->tenant);
            $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create([
                'slug' => 'user_ep_no_perm_role',
            ]);
            userEndpointAssignRole($user, $role);
            // No user.view permission granted

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/users')
                ->assertStatus(403);
        });

        it('GET /users/{uuid} returns 403 when authenticated user lacks user.view permission', function () {
            $user = userEndpointCreateTenantUser($this->tenant);
            $role = RoleModel::factory()->forTenant($this->tenant)->atLevel(5)->create([
                'slug' => 'user_ep_show_no_perm',
            ]);
            userEndpointAssignRole($user, $role);

            $target = userEndpointCreateTenantUser($this->tenant);

            $this->actingAs($user)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/users/{$target->uuid}")
                ->assertStatus(403);
        });

        it('owner bypasses permission check on GET /users', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/users')
                ->assertStatus(200);
        });

        it('owner bypasses permission check on GET /users/{uuid}', function () {
            $target = userEndpointCreateTenantUser($this->tenant);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/users/{$target->uuid}")
                ->assertStatus(200);
        });
    });

    // -------------------------------------------------------------------------
    // GET /users — list endpoint
    // -------------------------------------------------------------------------
    describe('GET /users', function () {
        it('returns 200 with paginated envelope containing meta.pagination and data', function () {
            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/users');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data',
                    'meta' => [
                        'pagination' => [
                            'total',
                            'per_page',
                            'current_page',
                            'last_page',
                        ],
                    ],
                ]);
        });

        it('returns list items with the expected shape', function () {
            $user = userEndpointCreateTenantUser($this->tenant, [
                'first_name' => 'Alicia',
                'last_name_paternal' => 'Rojas',
                'last_name_maternal' => 'Méndez',
                'email' => 'alicia.rojas@example.com',
                'status' => 'active',
            ]);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/users');

            $response->assertStatus(200);

            // At least one item should match the created user
            $items = $response->json('data');
            $uuids = array_column($items, 'uuid');
            expect($uuids)->toContain($user->uuid);

            // Find this user's item and assert shape
            $item = collect($items)->firstWhere('uuid', $user->uuid);
            expect($item)->toHaveKeys(['uuid', 'full_name', 'email', 'phone', 'status', 'roles', 'created_at']);
            expect($item)->not->toHaveKey('id');
            expect($item['full_name'])->toBe('Alicia Rojas Méndez');
            expect($item['email'])->toBe('alicia.rojas@example.com');
        });

        it('filters by q (search) parameter', function () {
            userEndpointCreateTenantUser($this->tenant, [
                'first_name' => 'Búsqueda',
                'last_name_paternal' => 'Única',
                'email' => 'search_match@example.com',
            ]);
            userEndpointCreateTenantUser($this->tenant, [
                'first_name' => 'Otro',
                'email' => 'other_user@example.com',
            ]);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/users?q=Búsqueda');

            $response->assertStatus(200);

            $items = $response->json('data');
            $emails = array_column($items, 'email');
            expect($emails)->toContain('search_match@example.com');
            expect($emails)->not->toContain('other_user@example.com');
        });

        it('filters by filter[role] parameter', function () {
            $studentRole = RoleModel::factory()->forTenant($this->tenant)->create(['slug' => 'student_ep_role']);
            $otherRole = RoleModel::factory()->forTenant($this->tenant)->create(['slug' => 'other_ep_role']);

            $student = userEndpointCreateTenantUser($this->tenant, ['email' => 'student_ep@example.com']);
            $other = userEndpointCreateTenantUser($this->tenant, ['email' => 'other_ep@example.com']);

            userEndpointAssignRole($student, $studentRole);
            userEndpointAssignRole($other, $otherRole);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/users?filter[role]=student_ep_role');

            $response->assertStatus(200);

            $emails = array_column($response->json('data'), 'email');
            expect($emails)->toContain('student_ep@example.com');
            expect($emails)->not->toContain('other_ep@example.com');
        });

        it('returns only users without an active role when filter[role]=none', function () {
            $role = RoleModel::factory()->forTenant($this->tenant)->create(['slug' => 'assigned_none_role']);

            $withRole = userEndpointCreateTenantUser($this->tenant, ['email' => 'with_role_none@example.com']);
            $withoutRole = userEndpointCreateTenantUser($this->tenant, ['email' => 'without_role_none@example.com']);

            userEndpointAssignRole($withRole, $role);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/users?filter[role]=none');

            $response->assertStatus(200);

            $emails = array_column($response->json('data'), 'email');
            expect($emails)->toContain('without_role_none@example.com');
            expect($emails)->not->toContain('with_role_none@example.com');
            expect($withoutRole->fresh())->not->toBeNull();
        });

        it('treats a user whose only assignment is revoked as unassigned (filter[role]=none)', function () {
            $role = RoleModel::factory()->forTenant($this->tenant)->create(['slug' => 'revoked_none_role']);

            $revoked = userEndpointCreateTenantUser($this->tenant, ['email' => 'revoked_none@example.com']);

            UserRoleAssignment::factory()
                ->forUser($revoked)
                ->forRole($role)
                ->create(['school_id' => null, 'revoked_at' => now()]);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/users?filter[role]=none');

            $response->assertStatus(200);

            $emails = array_column($response->json('data'), 'email');
            expect($emails)->toContain('revoked_none@example.com');
        });

        it('filters by filter[status] parameter', function () {
            userEndpointCreateTenantUser($this->tenant, [
                'email' => 'active_status@example.com',
                'status' => 'active',
            ]);
            userEndpointCreateTenantUser($this->tenant, [
                'email' => 'inactive_status@example.com',
                'status' => 'inactive',
            ]);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/users?filter[status]=inactive');

            $response->assertStatus(200);

            $emails = array_column($response->json('data'), 'email');
            expect($emails)->toContain('inactive_status@example.com');
            expect($emails)->not->toContain('active_status@example.com');
        });

        it('filters by filter[status]=pending matching unverified emails (virtual status)', function () {
            // `pending` is virtual: it means email_verified_at IS NULL, not a status column value.
            userEndpointCreateTenantUser($this->tenant, [
                'email' => 'verified_user@example.com',
                'email_verified_at' => now(),
            ]);
            userEndpointCreateTenantUser($this->tenant, [
                'email' => 'pending_user@example.com',
                'email_verified_at' => null,
            ]);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/users?filter[status]=pending');

            $response->assertStatus(200);

            $emails = array_column($response->json('data'), 'email');
            expect($emails)->toContain('pending_user@example.com');
            expect($emails)->not->toContain('verified_user@example.com');
        });

        it('scopes list to the school when X-School-Uuid header is provided', function () {
            $school = School::factory()->forTenant($this->tenant)->create();
            $role = RoleModel::factory()->forTenant($this->tenant)->create(['slug' => 'school_ep_role']);

            $inSchool = userEndpointCreateTenantUser($this->tenant, ['email' => 'in_school_ep@example.com']);
            $notInSchool = userEndpointCreateTenantUser($this->tenant, ['email' => 'not_in_school_ep@example.com']);
            $otherSchool = School::factory()->forTenant($this->tenant)->create();

            userEndpointAssignRole($inSchool, $role, $school->id);
            userEndpointAssignRole($notInSchool, $role, $otherSchool->id);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->withHeader('X-School-Uuid', $school->uuid)
                ->getJson('/api/tenant/users');

            $response->assertStatus(200);

            $emails = array_column($response->json('data'), 'email');
            expect($emails)->toContain('in_school_ep@example.com');
            expect($emails)->not->toContain('not_in_school_ep@example.com');
        });

        it('does not expose internal id in list items', function () {
            userEndpointCreateTenantUser($this->tenant);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/users');

            $response->assertStatus(200);

            foreach ($response->json('data') as $item) {
                expect($item)->not->toHaveKey('id');
                expect($item)->toHaveKey('uuid');
            }
        });

        it('paginates when per_page and page are supplied', function () {
            for ($i = 1; $i <= 5; $i++) {
                userEndpointCreateTenantUser($this->tenant, ['email' => "pag{$i}_ep@example.com"]);
            }

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/users?per_page=2&page=1');

            $response->assertStatus(200);

            expect(count($response->json('data')))->toBe(2);
            expect($response->json('meta.pagination.per_page'))->toBe(2);
            expect($response->json('meta.pagination.current_page'))->toBe(1);
        });
    });

    // -------------------------------------------------------------------------
    // Authority-driven school scope (server-side, not header-driven)
    // -------------------------------------------------------------------------
    describe('authority-driven school scope', function () {
        it('owner without a header sees users across every school in the tenant', function () {
            $schoolA = School::factory()->forTenant($this->tenant)->create();
            $schoolB = School::factory()->forTenant($this->tenant)->create();
            $role = RoleModel::factory()->forTenant($this->tenant)->create(['slug' => 'owner_scope_role']);

            $userA = userEndpointCreateTenantUser($this->tenant, ['email' => 'owner_sees_a@example.com']);
            $userB = userEndpointCreateTenantUser($this->tenant, ['email' => 'owner_sees_b@example.com']);
            userEndpointAssignRole($userA, $role, $schoolA->id);
            userEndpointAssignRole($userB, $role, $schoolB->id);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/users');

            $response->assertStatus(200);
            $emails = array_column($response->json('data'), 'email');
            expect($emails)->toContain('owner_sees_a@example.com');
            expect($emails)->toContain('owner_sees_b@example.com');
        });

        it('lets a gestor see users across all schools they manage without a header', function () {
            $schoolA = School::factory()->forTenant($this->tenant)->create();
            $schoolB = School::factory()->forTenant($this->tenant)->create();
            $schoolC = School::factory()->forTenant($this->tenant)->create();

            $gestorRole = RoleModel::factory()->forTenant($this->tenant)->create(['slug' => 'gestor_scope_role']);
            userEndpointGrantPermission($gestorRole, 'user.view');

            $gestor = userEndpointCreateTenantUser($this->tenant, ['email' => 'gestor_scope@example.com']);
            userEndpointAssignRole($gestor, $gestorRole, $schoolA->id);
            userEndpointAssignRole($gestor, $gestorRole, $schoolB->id);

            $memberRole = RoleModel::factory()->forTenant($this->tenant)->create(['slug' => 'member_scope_role']);
            $userA = userEndpointCreateTenantUser($this->tenant, ['email' => 'member_a@example.com']);
            $userB = userEndpointCreateTenantUser($this->tenant, ['email' => 'member_b@example.com']);
            $userC = userEndpointCreateTenantUser($this->tenant, ['email' => 'member_c@example.com']);
            userEndpointAssignRole($userA, $memberRole, $schoolA->id);
            userEndpointAssignRole($userB, $memberRole, $schoolB->id);
            userEndpointAssignRole($userC, $memberRole, $schoolC->id);

            $response = $this->actingAs($gestor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/users');

            $response->assertStatus(200);
            $emails = array_column($response->json('data'), 'email');
            expect($emails)->toContain('member_a@example.com');
            expect($emails)->toContain('member_b@example.com');
            expect($emails)->not->toContain('member_c@example.com');
        });

        it('scopes a director to their own school when no header is sent (no tenant-wide leak)', function () {
            $schoolX = School::factory()->forTenant($this->tenant)->create();
            $schoolY = School::factory()->forTenant($this->tenant)->create();

            $directorRole = RoleModel::factory()->forTenant($this->tenant)->create(['slug' => 'director_scope_role']);
            userEndpointGrantPermission($directorRole, 'user.view');

            $director = userEndpointCreateTenantUser($this->tenant, ['email' => 'director_scope@example.com']);
            userEndpointAssignRole($director, $directorRole, $schoolX->id);

            $memberRole = RoleModel::factory()->forTenant($this->tenant)->create(['slug' => 'member_xy_role']);
            $inX = userEndpointCreateTenantUser($this->tenant, ['email' => 'member_x@example.com']);
            $inY = userEndpointCreateTenantUser($this->tenant, ['email' => 'member_y@example.com']);
            userEndpointAssignRole($inX, $memberRole, $schoolX->id);
            userEndpointAssignRole($inY, $memberRole, $schoolY->id);

            $response = $this->actingAs($director)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/users');

            $response->assertStatus(200);
            $emails = array_column($response->json('data'), 'email');
            expect($emails)->toContain('member_x@example.com');
            expect($emails)->not->toContain('member_y@example.com');
        });

        it('returns 403 when a non-owner requests a school outside their access', function () {
            $school = School::factory()->forTenant($this->tenant)->create();

            // Tenant-level role (school_id null) that can view users but is tied to no school.
            // The gate passes (tenant-level user.view applies to any school) so the request
            // reaches the use case, where the school-access guard rejects it.
            $tenantRole = RoleModel::factory()->forTenant($this->tenant)->create(['slug' => 'tenant_view_role']);
            userEndpointGrantPermission($tenantRole, 'user.view');

            $actor = userEndpointCreateTenantUser($this->tenant, ['email' => 'tenant_actor@example.com']);
            userEndpointAssignRole($actor, $tenantRole, null);

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->withHeader('X-School-Uuid', $school->uuid)
                ->getJson('/api/tenant/users')
                ->assertStatus(403);
        });
    });

    // -------------------------------------------------------------------------
    // GET /users/{uuid} — detail endpoint
    // -------------------------------------------------------------------------
    describe('GET /users/{uuid}', function () {
        it('returns 200 with the full detail shape for an existing user', function () {
            $user = userEndpointCreateTenantUser($this->tenant, [
                'first_name' => 'Detalle',
                'last_name_paternal' => 'Completo',
                'last_name_maternal' => 'García',
                'email' => 'detalle@example.com',
                'status' => 'active',
            ]);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/users/{$user->uuid}");

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'uuid',
                        'first_name',
                        'last_name_paternal',
                        'last_name_maternal',
                        'full_name',
                        'email',
                        'phone',
                        'status',
                        'roles',
                        'created_at',
                    ],
                ]);

            expect($response->json('data.uuid'))->toBe($user->uuid);
            expect($response->json('data.full_name'))->toBe('Detalle Completo García');
            expect($response->json('data.email'))->toBe('detalle@example.com');
        });

        it('does not expose internal id in detail response', function () {
            $user = userEndpointCreateTenantUser($this->tenant);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/users/{$user->uuid}");

            $response->assertStatus(200);
            expect($response->json('data'))->not->toHaveKey('id');
            expect($response->json('data'))->toHaveKey('uuid');
        });

        it('returns 404 for a uuid that does not exist', function () {
            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/users/00000000-0000-0000-0000-000000000000')
                ->assertStatus(404);
        });

        it('returns 404 when the uuid belongs to a user in a different tenant', function () {
            $otherTenant = Tenant::factory()->create();
            $foreignUser = userEndpointCreateTenantUser($otherTenant);

            $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/users/{$foreignUser->uuid}")
                ->assertStatus(404);
        });

        it('includes the roles array in the detail response with correct shape', function () {
            $school = School::factory()->forTenant($this->tenant)->create();
            $role = RoleModel::factory()->forTenant($this->tenant)->create([
                'slug' => 'detail_role_ep',
                'name' => 'Detail Role',
            ]);

            $user = userEndpointCreateTenantUser($this->tenant, ['email' => 'detail_roles@example.com']);
            userEndpointAssignRole($user, $role, $school->id);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/users/{$user->uuid}");

            $response->assertStatus(200);

            $roles = $response->json('data.roles');
            expect($roles)->toHaveCount(1);
            expect($roles[0])->toHaveKeys(['role_uuid', 'slug', 'name', 'school_uuid']);
            expect($roles[0]['role_uuid'])->toBe($role->uuid);
            expect($roles[0]['slug'])->toBe('detail_role_ep');
            expect($roles[0]['name'])->toBe('Detail Role');
            expect($roles[0]['school_uuid'])->toBe($school->uuid);
        });

        it('returns roles with school_uuid null for tenant-level assignments', function () {
            $role = RoleModel::factory()->forTenant($this->tenant)->create([
                'slug' => 'tenant_detail_role_ep',
                'name' => 'Tenant Detail Role',
            ]);

            $user = userEndpointCreateTenantUser($this->tenant, ['email' => 'detail_tenant_role@example.com']);
            userEndpointAssignRole($user, $role, null);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson("/api/tenant/users/{$user->uuid}");

            $response->assertStatus(200);

            $roles = $response->json('data.roles');
            expect($roles[0]['school_uuid'])->toBeNull();
        });
    });

    // -------------------------------------------------------------------------
    // Tenant isolation via HTTP
    // -------------------------------------------------------------------------
    describe('tenant isolation', function () {
        it('a user authenticated against tenant A cannot see users from tenant B', function () {
            $tenantB = Tenant::factory()->create();
            $userInB = userEndpointCreateTenantUser($tenantB, ['email' => 'tenant_b_user@example.com']);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/tenant/users');

            $response->assertStatus(200);

            $emails = array_column($response->json('data'), 'email');
            expect($emails)->not->toContain('tenant_b_user@example.com');
        });
    });

    // -------------------------------------------------------------------------
    // GET /users/stats — directory stats cards (total + pending)
    // -------------------------------------------------------------------------
    describe('GET /users/stats', function () {
        it('returns total and pending counts for the directory scope', function () {
            $role = RoleModel::factory()->forTenant($this->tenant)->create(['slug' => 'stats_role']);

            // 2 verified + 1 unverified (pending), all within the role scope.
            $a = userEndpointCreateTenantUser($this->tenant, ['email' => 'sv_a@example.com', 'email_verified_at' => now()]);
            $b = userEndpointCreateTenantUser($this->tenant, ['email' => 'sv_b@example.com', 'email_verified_at' => now()]);
            $pending = userEndpointCreateTenantUser($this->tenant, ['email' => 'sp@example.com', 'email_verified_at' => null]);
            userEndpointAssignRole($a, $role);
            userEndpointAssignRole($b, $role);
            userEndpointAssignRole($pending, $role);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/users/stats?filter[role]=stats_role');

            $response->assertStatus(200)
                ->assertJsonStructure(['success', 'data' => ['total', 'pending']]);

            expect($response->json('data.total'))->toBe(3);
            expect($response->json('data.pending'))->toBe(1);
        });

        it('scopes the counts to the active school via X-School-Uuid', function () {
            $role = RoleModel::factory()->forTenant($this->tenant)->create(['slug' => 'stats_school_role']);
            $schoolA = School::factory()->forTenant($this->tenant)->create();
            $schoolB = School::factory()->forTenant($this->tenant)->create();

            $inA = userEndpointCreateTenantUser($this->tenant, ['email' => 'stat_in_a@example.com', 'email_verified_at' => null]);
            $inB = userEndpointCreateTenantUser($this->tenant, ['email' => 'stat_in_b@example.com', 'email_verified_at' => null]);
            userEndpointAssignRole($inA, $role, $schoolA->id);
            userEndpointAssignRole($inB, $role, $schoolB->id);

            $response = $this->actingAs($this->owner)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->withHeader('X-School-Uuid', $schoolA->uuid)
                ->getJson('/api/users/stats?filter[role]=stats_school_role');

            $response->assertStatus(200);
            expect($response->json('data.total'))->toBe(1);
            expect($response->json('data.pending'))->toBe(1);
        });

        it('returns 403 when the actor lacks user.view', function () {
            $actor = userEndpointCreateTenantUser($this->tenant);

            $this->actingAs($actor)
                ->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->getJson('/api/users/stats')
                ->assertStatus(403);
        });
    });
});
