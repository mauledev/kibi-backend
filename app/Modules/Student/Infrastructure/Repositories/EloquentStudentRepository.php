<?php

namespace App\Modules\Student\Infrastructure\Repositories;

use App\Common\Tenant\TenantContext;
use App\Models\StudentProfile;
use App\Models\User as UserModel;
use App\Modules\Student\Domain\Contracts\StudentRepositoryInterface;
use App\Modules\Student\Domain\Criteria\StudentListCriteria;
use App\Modules\Student\Domain\Entities\Student;
use App\Modules\Student\Domain\ValueObjects\StudentUpdateData;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent implementation of StudentRepositoryInterface.
 *
 * Tenant isolation is applied on every query via TenantContext. The queries
 * join users and student_profiles — the student entity aggregates fields from
 * both tables. Eloquent models never leave this class; the toEntity() mapper
 * converts every row to a Domain Student entity before returning.
 */
class EloquentStudentRepository implements StudentRepositoryInterface
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function create(
        string $userUuid,
        ?string $birthDate,
        ?string $nationalId,
        ?string $enrollmentNumber,
        ?string $gender,
        ?string $bloodType,
        ?int $groupId,
    ): Student {
        $userId = UserModel::where('uuid', $userUuid)
            ->where('tenant_id', $this->context->tenantId)
            ->value('id');

        $profile = StudentProfile::create([
            'user_id' => $userId,
            'birth_date' => $birthDate,
            'national_id' => $nationalId,
            'enrollment_number' => $enrollmentNumber,
            'gender' => $gender,
            'blood_type' => $bloodType,
            'group_id' => $groupId,
        ]);

        $row = $this->buildBaseQuery()
            ->where('sp.id', $profile->id)
            ->first();

        return $this->toEntity($row);
    }

    /**
     * {@inheritDoc}
     */
    public function update(int $userId, StudentUpdateData $data): Student
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

        // Build the student_profiles update payload.
        $profileUpdates = array_filter([
            'birth_date' => $data->birthDate,
            'national_id' => $data->nationalId,
            'enrollment_number' => $data->enrollmentNumber,
            'gender' => $data->gender,
            'blood_type' => $data->bloodType,
            'group_id' => $data->groupId,
        ], fn ($value) => $value !== null);

        if ($profileUpdates !== []) {
            StudentProfile::where('user_id', $userId)->update($profileUpdates);
        }

        $row = $this->buildBaseQuery()
            ->where('u.id', $userId)
            ->first();

        return $this->toEntity($row);
    }

    /**
     * {@inheritDoc}
     */
    public function findAllPaginated(StudentListCriteria $criteria): array
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

        // School scope — filter via user_role_assignments with slug = 'student'
        if ($criteria->isOwner) {
            if ($criteria->schoolId !== null) {
                $query->whereExists(function ($sub) use ($criteria): void {
                    $sub->select(DB::raw(1))
                        ->from('user_role_assignments as ura')
                        ->join('roles as r', 'r.id', '=', 'ura.role_id')
                        ->whereColumn('ura.user_id', 'u.id')
                        ->where('r.slug', 'student')
                        ->where('ura.school_id', $criteria->schoolId)
                        ->whereNull('ura.revoked_at');
                });
            }
            // else: owner with no school filter sees all students in the tenant
        } else {
            // Non-owner: restrict to accessible schools
            $schoolIds = $criteria->schoolId !== null
                ? [$criteria->schoolId]
                : $criteria->accessibleSchoolIds;

            $query->whereExists(function ($sub) use ($schoolIds): void {
                $sub->select(DB::raw(1))
                    ->from('user_role_assignments as ura')
                    ->join('roles as r', 'r.id', '=', 'ura.role_id')
                    ->whereColumn('ura.user_id', 'u.id')
                    ->where('r.slug', 'student')
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
    public function findByUserUuid(string $uuid): ?Student
    {
        $row = $this->buildBaseQuery()
            ->where('u.uuid', $uuid)
            ->first();

        return $row !== null ? $this->toEntity($row) : null;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build the base query joining users and student_profiles, scoped to the current tenant.
     *
     * The query selects all fields needed to construct a Student entity. The tenant
     * scope on users.tenant_id is always applied first, as required by the architecture.
     */
    private function buildBaseQuery(): Builder
    {
        return DB::table('users as u')
            ->join('student_profiles as sp', 'sp.user_id', '=', 'u.id')
            ->leftJoin('groups as g', 'g.id', '=', 'sp.group_id')
            ->where('u.tenant_id', $this->context->tenantId)
            ->where('u.is_staff', false)
            ->whereNull('u.deleted_at')
            ->whereNull('sp.deleted_at')
            ->select([
                'sp.id as profile_id',
                'sp.uuid as profile_uuid',
                'u.id as user_id',
                'u.uuid as user_uuid',
                'u.email',
                'u.first_name',
                'u.last_name_paternal',
                'u.last_name_maternal',
                'u.phone',
                'u.status',
                'sp.birth_date',
                'sp.national_id',
                'sp.enrollment_number',
                'sp.gender',
                'sp.blood_type',
                'g.uuid as group_uuid',
                'g.name as group_name',
                'sp.created_at',
            ]);
    }

    /**
     * Map a raw query result row to a Domain Student entity.
     *
     * @param  object  $row  A row returned by buildBaseQuery().
     */
    private function toEntity(object $row): Student
    {
        return new Student(
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
            birthDate: $row->birth_date ?? null,
            nationalId: $row->national_id ?? null,
            enrollmentNumber: $row->enrollment_number ?? null,
            gender: $row->gender ?? null,
            bloodType: $row->blood_type ?? null,
            groupUuid: $row->group_uuid ?? null,
            groupName: $row->group_name ?? null,
            createdAt: new \DateTime($row->created_at),
        );
    }
}
