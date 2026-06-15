<?php

namespace App\Modules\Tutor\Infrastructure\Repositories;

use App\Common\Tenant\TenantContext;
use App\Models\TutorProfile;
use App\Models\User as UserModel;
use App\Modules\Tutor\Domain\Contracts\TutorRepositoryInterface;
use App\Modules\Tutor\Domain\Criteria\TutorListCriteria;
use App\Modules\Tutor\Domain\Entities\Tutor;
use App\Modules\Tutor\Domain\ValueObjects\TutorUpdateData;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent implementation of TutorRepositoryInterface.
 *
 * Tenant isolation is applied on every query via TenantContext. The queries
 * join users and tutor_profiles — the Tutor entity aggregates fields from
 * both tables. Eloquent models never leave this class; the toEntity() mapper
 * converts every row to a Domain Tutor entity before returning.
 */
class EloquentTutorRepository implements TutorRepositoryInterface
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function create(string $userUuid, ?string $occupation): Tutor
    {
        $userId = UserModel::where('uuid', $userUuid)
            ->where('tenant_id', $this->context->tenantId)
            ->value('id');

        $profile = TutorProfile::create([
            'user_id' => $userId,
            'occupation' => $occupation,
        ]);

        $row = $this->buildBaseQuery()
            ->where('tp.id', $profile->id)
            ->first();

        return $this->toEntity($row);
    }

    /**
     * {@inheritDoc}
     */
    public function update(int $userId, TutorUpdateData $data): Tutor
    {
        // Build the users update payload — only update fields that were explicitly provided.
        $userUpdates = array_filter([
            'first_name' => $data->firstName,
            'last_name_paternal' => $data->lastNamePaternal,
            'last_name_maternal' => $data->lastNameMaternal,
            'phone' => $data->phone,
        ], fn ($value) => $value !== null);

        if ($userUpdates !== []) {
            UserModel::where('id', $userId)
                ->where('tenant_id', $this->context->tenantId)
                ->update($userUpdates);
        }

        if ($data->occupation !== null) {
            TutorProfile::where('user_id', $userId)->update(['occupation' => $data->occupation]);
        }

        $row = $this->buildBaseQuery()
            ->where('u.id', $userId)
            ->first();

        return $this->toEntity($row);
    }

    /**
     * {@inheritDoc}
     */
    public function findAllPaginated(TutorListCriteria $criteria): array
    {
        $query = $this->buildBaseQuery();

        // Search filter
        if ($criteria->search !== null && $criteria->search !== '') {
            $term = "%{$criteria->search}%";
            $query->where(function ($q) use ($term): void {
                $q->where('u.first_name', 'ILIKE', $term)
                    ->orWhere('u.last_name_paternal', 'ILIKE', $term)
                    ->orWhere('u.email', 'ILIKE', $term);
            });
        }

        // School scope — filter via user_role_assignments with slug = 'tutor'
        if ($criteria->isOwner) {
            if ($criteria->requestedSchoolId !== null) {
                $query->whereExists(function ($sub) use ($criteria): void {
                    $sub->select(DB::raw(1))
                        ->from('user_role_assignments as ura')
                        ->join('roles as r', 'r.id', '=', 'ura.role_id')
                        ->whereColumn('ura.user_id', 'u.id')
                        ->where('r.slug', 'tutor')
                        ->where('ura.school_id', $criteria->requestedSchoolId)
                        ->whereNull('ura.revoked_at');
                });
            }
            // else: owner with no school filter sees all tutors in the tenant
        } else {
            // Non-owner: restrict to accessible schools
            $schoolIds = $criteria->requestedSchoolId !== null
                ? [$criteria->requestedSchoolId]
                : $criteria->accessibleSchoolIds;

            $query->whereExists(function ($sub) use ($schoolIds): void {
                $sub->select(DB::raw(1))
                    ->from('user_role_assignments as ura')
                    ->join('roles as r', 'r.id', '=', 'ura.role_id')
                    ->whereColumn('ura.user_id', 'u.id')
                    ->where('r.slug', 'tutor')
                    ->whereIn('ura.school_id', $schoolIds)
                    ->whereNull('ura.revoked_at');
            });
        }

        // Count for pagination
        $total = (clone $query)->count();

        $rows = $query
            ->orderBy('u.last_name_paternal')
            ->orderBy('u.first_name')
            ->offset(($criteria->page - 1) * $criteria->perPage)
            ->limit($criteria->perPage)
            ->get();

        $lastPage = max(1, (int) ceil($total / $criteria->perPage));

        return [
            'items' => $rows->map(fn (object $row) => $this->toEntity($row))->all(),
            'total' => $total,
            'per_page' => $criteria->perPage,
            'current_page' => $criteria->page,
            'last_page' => $lastPage,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function findByUserUuid(string $uuid): ?Tutor
    {
        $row = $this->buildBaseQuery()
            ->where('u.uuid', $uuid)
            ->first();

        return $row !== null ? $this->toEntity($row) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function hasActiveLink(int $studentUserId): bool
    {
        return DB::table('student_tutors')
            ->where('student_user_id', $studentUserId)
            ->whereNull('unlinked_at')
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function linkToStudent(int $tutorUserId, int $studentUserId, ?string $relationship): void
    {
        DB::table('student_tutors')->insert([
            'tutor_user_id' => $tutorUserId,
            'student_user_id' => $studentUserId,
            'relationship' => $relationship,
            'linked_at' => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build the base query joining users and tutor_profiles, scoped to the current tenant.
     *
     * The query selects all fields needed to construct a Tutor entity. The tenant
     * scope on users.tenant_id is always applied first, as required by the architecture.
     */
    private function buildBaseQuery(): Builder
    {
        return DB::table('users as u')
            ->join('tutor_profiles as tp', 'tp.user_id', '=', 'u.id')
            ->where('u.tenant_id', $this->context->tenantId)
            ->where('u.is_staff', false)
            ->whereNull('u.deleted_at')
            ->whereNull('tp.deleted_at')
            ->select([
                'tp.id as profile_id',
                'tp.uuid as profile_uuid',
                'u.id as user_id',
                'u.uuid as user_uuid',
                'u.email',
                'u.first_name',
                'u.last_name_paternal',
                'u.last_name_maternal',
                'u.phone',
                'u.status',
                'tp.occupation',
                'tp.created_at',
            ]);
    }

    /**
     * Map a raw query result row to a Domain Tutor entity.
     *
     * @param  object  $row  A row returned by buildBaseQuery().
     */
    private function toEntity(object $row): Tutor
    {
        return new Tutor(
            id: (int) $row->profile_id,
            uuid: $row->profile_uuid,
            userId: (int) $row->user_id,
            userUuid: $row->user_uuid,
            email: $row->email,
            firstName: $row->first_name,
            lastNamePaternal: $row->last_name_paternal,
            lastNameMaternal: $row->last_name_maternal ?? null,
            phone: $row->phone ?? null,
            status: $row->status,
            occupation: $row->occupation ?? null,
            createdAt: new \DateTime($row->created_at),
        );
    }
}
