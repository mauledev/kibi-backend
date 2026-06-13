<?php

namespace App\Modules\User\Infrastructure\Repositories;

use App\Common\Tenant\TenantContext;
use App\Models\User as UserModel;
use App\Models\UserRoleAssignment;
use App\Modules\User\Domain\Contracts\UserRepositoryInterface;
use App\Modules\User\Domain\Criteria\UserListCriteria;
use App\Modules\User\Domain\Criteria\UserStatsCriteria;
use App\Modules\User\Domain\Entities\RoleAssignment;
use App\Modules\User\Domain\Entities\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent implementation of the read-only UserRepositoryInterface.
 *
 * Scoping rules applied unconditionally on every query:
 *  1. tenant_id = TenantContext::tenantId  — isolates to the current tenant.
 *  2. is_staff = false                      — never returns Softlinkia staff users.
 *
 * Role assignments are always eager-loaded with their role and school relations
 * to prevent N+1 queries when the entity mapper builds the roles collection.
 * Only active assignments (revoked_at IS NULL) are loaded.
 *
 * Eloquent models never leave this class — the toDomain() mapper converts
 * every UserModel to a Domain User entity before returning.
 */
class EloquentUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly TenantContext $context,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * Filters applied in order:
     *  - Tenant scope (always first)
     *  - search  → ILIKE on first_name, last_name_paternal, last_name_maternal, email
     *  - status  → exact match on users.status
     *  - school + role slugs → nested whereHas on roleAssignments to ensure both
     *    conditions hold on the same assignment row (not across different rows)
     */
    public function findAllPaginated(UserListCriteria $criteria): array
    {
        $query = $this->baseQuery();

        $this->applySearch($query, $criteria->search);
        $this->applyStatusFilter($query, $criteria->status);

        if ($criteria->unassigned) {
            $this->applyUnassignedFilter($query);
        } else {
            $this->applyRoleAndSchoolFilter($query, $criteria->roleSlugs, $criteria->schoolIds);
        }

        $this->applyEagerLoad($query, $criteria->schoolIds);

        $paginator = $query->paginate($criteria->perPage, ['*'], 'page', $criteria->page);

        return [
            'items' => array_map(
                fn(UserModel $m) => $this->toDomain($m),
                $paginator->items()
            ),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function findByUuid(string $uuid): ?User
    {
        $model = $this->baseQuery()
            ->where('uuid', $uuid)
            ->with($this->roleEagerLoad(null))
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getStats(UserStatsCriteria $criteria): array
    {
        // Two COUNT queries over the same directory scope — no rows are loaded.
        // `pending` reuses the virtual-status rule: an unverified email.
        $total = $this->statsScopedQuery($criteria)->count();
        $pending = $this->statsScopedQuery($criteria)
            ->whereNull('email_verified_at')
            ->count();

        return [
            'total' => $total,
            'pending' => $pending,
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * A fresh base query scoped to the directory (tenant + non-staff + role/school
     * scope). Built fresh on each call so the two COUNT queries never share state.
     *
     * @return Builder<UserModel>
     */
    private function statsScopedQuery(UserStatsCriteria $criteria): Builder
    {
        $query = $this->baseQuery();
        $this->applyRoleAndSchoolFilter($query, $criteria->roleSlugs, $criteria->schoolIds);

        return $query;
    }

    /**
     * Return a base query already scoped to the current tenant's non-staff users.
     *
     * @return Builder<UserModel>
     */
    private function baseQuery(): Builder
    {
        return UserModel::where('tenant_id', $this->context->tenantId)
            ->where('is_staff', false);
    }

    /**
     * Apply a case-insensitive full-text search across name and email columns.
     *
     * Uses Postgres ILIKE to match partial strings against first_name,
     * last_name_paternal, last_name_maternal, and email.
     *
     * @param  Builder<UserModel>  $query
     */
    private function applySearch(Builder $query, ?string $search): void
    {
        if ($search === null || $search === '') {
            return;
        }

        $term = "%{$search}%";

        $query->where(function (Builder $q) use ($term): void {
            $q->where('first_name', 'ILIKE', $term)
                ->orWhere('last_name_paternal', 'ILIKE', $term)
                ->orWhere('last_name_maternal', 'ILIKE', $term)
                ->orWhere('email', 'ILIKE', $term);
        });
    }

    /**
     * Apply a status filter when provided.
     *
     * `pending` is a VIRTUAL status: the API derives it from an unverified email
     * (see UserListResource), it is NOT a value stored in users.status. Filtering
     * by it must therefore match unverified users (email_verified_at IS NULL),
     * not a literal status-column value — keeping the filter symmetric with how
     * the resource presents the status.
     *
     * @param  Builder<UserModel>  $query
     */
    private function applyStatusFilter(Builder $query, ?string $status): void
    {
        if ($status === null) {
            return;
        }

        if ($status === 'pending') {
            $query->whereNull('email_verified_at');

            return;
        }

        $query->where('status', $status);
    }

    /**
     * Apply role-slug and school filters via a nested whereHas.
     *
     * When both roleSlugs and schoolIds are present, both conditions must hold on
     * the SAME assignment row. A single nested whereHas achieves this: it checks
     * that there exists at least one active assignment for this user where:
     *  - school_id IN schoolIds (when schoolIds is a non-null array)
     *  - role.slug is in roleSlugs (when roleSlugs is non-empty)
     *
     * School scope semantics for $schoolIds:
     *  - null  → no school restriction.
     *  - []    → empty whereIn forces the existence check to fail → no users returned.
     *  - [ids] → restrict to assignments in any of these schools.
     *
     * @param  Builder<UserModel>  $query
     * @param  array<int, string>  $roleSlugs
     * @param  array<int, int>|null  $schoolIds
     */
    private function applyRoleAndSchoolFilter(Builder $query, array $roleSlugs, ?array $schoolIds): void
    {
        $hasRoleFilter = count($roleSlugs) > 0;
        $hasSchoolFilter = $schoolIds !== null;

        if (!$hasRoleFilter && !$hasSchoolFilter) {
            return;
        }

        $query->whereHas('roleAssignments', function (Builder $q) use ($roleSlugs, $schoolIds, $hasRoleFilter, $hasSchoolFilter): void {
            $q->whereNull('revoked_at');

            if ($hasSchoolFilter) {
                /** @var array<int, int> $schoolIds */
                $q->whereIn('school_id', $schoolIds);
            }

            if ($hasRoleFilter) {
                $q->whereHas('role', function (Builder $r) use ($roleSlugs): void {
                    $r->whereIn('slug', $roleSlugs);
                });
            }
        });
    }

    /**
     * Restrict to users that have NO active role assignment.
     *
     * "Active" mirrors the rest of the module: revoked_at IS NULL. School scope is
     * intentionally ignored — a role-less user belongs to no school, so applying a
     * school filter would always exclude them. Tenant + is_staff scoping from the
     * base query still applies.
     *
     * @param  Builder<UserModel>  $query
     */
    private function applyUnassignedFilter(Builder $query): void
    {
        $query->whereDoesntHave('roleAssignments', function (Builder $q): void {
            $q->whereNull('revoked_at');
        });
    }

    /**
     * Constrained eager load for role assignments — active only, with role and school.
     *
     * @param  Builder<UserModel>  $query
     * @param  array<int, int>|null  $schoolIds
     */
    private function applyEagerLoad(Builder $query, ?array $schoolIds): void
    {
        $query->with($this->roleEagerLoad($schoolIds));
    }

    /**
     * Build the eager-load definition for role assignments.
     *
     * Loads only active assignments (revoked_at IS NULL). When schoolIds is a
     * non-null array, only assignments in those schools plus tenant-level
     * assignments (school_id IS NULL) are included in the roles collection of
     * each entity. When schoolIds is null, all active assignments are loaded.
     *
     * @param  array<int, int>|null  $schoolIds
     * @return array<string, \Closure>
     */
    private function roleEagerLoad(?array $schoolIds): array
    {
        return [
            // Eager-load constraint closures receive the Relation instance (HasMany),
            // NOT an Eloquent\Builder — type-hinting Builder here triggers a TypeError
            // at runtime. The relation forwards query-builder calls via its mixin.
            'roleAssignments' => function (HasMany $q) use ($schoolIds): void {
                $q->whereNull('revoked_at');

                if ($schoolIds !== null) {
                    $q->where(function (Builder $inner) use ($schoolIds): void {
                        $inner->whereIn('school_id', $schoolIds)
                            ->orWhereNull('school_id');
                    });
                }

                $q->with(['role', 'school']);
            },
        ];
    }

    /**
     * Map a loaded UserModel to a read-oriented Domain User entity.
     *
     * Assumes roleAssignments has been eager-loaded with role and school. The
     * eager load already scoped which assignments are present, so the mapper
     * simply converts whatever was loaded.
     */
    private function toDomain(UserModel $model): User
    {
        $roles = [];

        foreach ($model->roleAssignments as $assignment) {
            /** @var UserRoleAssignment $assignment */
            if ($assignment->role === null) {
                continue;
            }

            $roles[] = new RoleAssignment(
                roleUuid: $assignment->role->uuid,
                slug: $assignment->role->slug,
                name: $assignment->role->name,
                schoolUuid: $assignment->school?->uuid,
            );
        }

        return new User(
            id: $model->id,
            uuid: $model->uuid,
            email: $model->email,
            firstName: $model->first_name,
            lastNamePaternal: $model->last_name_paternal,
            lastNameMaternal: $model->last_name_maternal,
            phone: $model->phone,
            status: $model->status,
            createdAt: $model->created_at?->toDateTime() ?? new \DateTime,
            emailVerifiedAt: $model->email_verified_at?->toDateTime() ?? null,
            roles: $roles,
        );
    }
}
