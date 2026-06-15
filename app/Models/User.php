<?php

namespace App\Models;

use App\Modules\Roles\Domain\Enums\ActorRoleEnum;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * User Model — data access only, no business logic.
 * Business logic lives in App\Modules\Auth\Domain\Entities\User.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $tenant_id
 * @property bool $is_staff
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password_hash
 * @property string|null $google_id
 * @property string|null $microsoft_id
 * @property string $first_name
 * @property string $last_name_paternal
 * @property string|null $last_name_maternal
 * @property string|null $phone
 * @property string $status
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected static function booting(): void
    {
        static::creating(function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    protected $fillable = [
        'uuid',
        'tenant_id',
        'is_staff',
        'email',
        'email_verified_at',
        'password_hash',
        'google_id',
        'microsoft_id',
        'first_name',
        'last_name_paternal',
        'last_name_maternal',
        'phone',
        'status',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'tenant_id' => 'integer',
        'is_staff' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /** @return BelongsTo<Tenant, covariant User> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<UserRoleAssignment, $this> */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
    }

    /** @var array<string|int, Collection<int, UserRoleAssignment>> */
    private array $cachedAssignments = [];

    /**
     * Return all active role assignments (revoked_at IS NULL), optionally scoped to a school.
     * When schoolId is provided, returns assignments for that school AND tenant-level assignments.
     * Result is memoized per school for the lifetime of the request.
     *
     * @return Collection<int, UserRoleAssignment>
     */
    public function activeAssignments(?int $schoolId = null): Collection
    {
        $cacheKey = $schoolId ?? 'tenant';

        if (! isset($this->cachedAssignments[$cacheKey])) {
            $query = $this->roleAssignments()->whereNull('revoked_at');

            if ($schoolId !== null) {
                $query->where(function ($q) use ($schoolId) {
                    $q->where('school_id', $schoolId)->orWhereNull('school_id');
                });
            }

            $this->cachedAssignments[$cacheKey] = $query
                ->with(['role', 'role.permissions', 'denials'])
                ->get();
        }

        return $this->cachedAssignments[$cacheKey];
    }

    /**
     * Return all active Role models for this user.
     *
     * @return Collection<int, Role>
     */
    public function activeRoles(): Collection
    {
        return $this->activeAssignments()
            ->map(fn (UserRoleAssignment $a) => $a->role)
            ->filter()
            ->values();
    }

    /**
     * Check whether the user holds ANY active assignment with the given role slug.
     * This does NOT depend on X-Active-Role — it checks all active assignments.
     */
    public function hasRole(string $slug): bool
    {
        return $this->activeAssignments()
            ->map(fn (UserRoleAssignment $a) => $a->role)
            ->filter()
            ->contains(fn (Role $role) => $role->slug === $slug);
    }

    /**
     * Return effective permission slugs for the given school.
     * Effective permissions = role permissions − denials for each assignment.
     * Pass null for tenant-level only (no school scope).
     *
     * @return array<string>
     */
    public function activePermissions(?int $schoolId = null): array
    {
        $permissions = [];

        foreach ($this->activeAssignments($schoolId) as $assignment) {
            $deniedIds = $assignment->denials->pluck('permission_id')->all();

            /** @var Role|null $role */
            $role = $assignment->role;

            if ($role === null) {
                continue;
            }

            foreach ($role->permissions as $permission) {
                if (! in_array($permission->id, $deniedIds, true)) {
                    $permissions[] = $permission->slug;
                }
            }
        }

        return array_unique($permissions);
    }

    /**
     * Return true if the user has the given permission slug for the given school.
     * Pass null for tenant-level check only.
     */
    public function hasPermissionTo(string $slug, ?int $schoolId = null): bool
    {
        return in_array($slug, $this->activePermissions($schoolId), true);
    }

    /**
     * Resolve the user's primary actor slug for hierarchy validation.
     * Returns the highest-authority slug the user holds, or 'unknown' if none.
     */
    public function resolveActorSlug(): string
    {
        foreach (ActorRoleEnum::orderedByAuthority() as $case) {
            if ($this->hasRole($case->value)) {
                return $case->value;
            }
        }

        return 'unknown';
    }

    /**
     * Return true if the user holds any active role with is_system_role = true.
     * Used by Gate::before to grant superadmin bypass on staff routes.
     */
    public function hasActiveSystemRole(): bool
    {
        return $this->activeAssignments()
            ->map(fn (UserRoleAssignment $a) => $a->role)
            ->filter()
            ->contains(fn (Role $role) => $role->is_system_role);
    }

    /**
     * Return true if the user has an active school_manager assignment for the given school.
     */
    public function isGestorOfSchool(int $schoolId): bool
    {
        return $this->activeAssignments($schoolId)->contains(
            fn (UserRoleAssignment $a) => $a->role !== null && $a->role->slug === 'school_manager'
        );
    }

    /**
     * Return the distinct school IDs this user can operate in, derived from their
     * active, school-scoped assignments. Tenant-level assignments (school_id IS NULL)
     * are excluded — they grant no specific school. Used to scope listings for
     * non-owner actors (gestor sees all managed schools, director sees their school).
     *
     * @return array<int, int>
     */
    public function accessibleSchoolIds(): array
    {
        return $this->activeAssignments()
            ->pluck('school_id')
            ->reject(fn (?int $id) => $id === null)
            ->unique()
            ->values()
            ->all();
    }
}
