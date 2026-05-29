<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
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

    /** @return HasMany<UserRoleAssignment, $this> */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
    }

    /** @var Collection<int, UserRoleAssignment>|null */
    private ?Collection $cachedAssignments = null;

    /**
     * Return all active role assignments (revoked_at IS NULL).
     * Result is memoized for the lifetime of the request — the DB is hit once
     * regardless of how many hasRole() / hasPermissionTo() / authorize() calls occur.
     *
     * @return Collection<int, UserRoleAssignment>
     */
    public function activeAssignments(): Collection
    {
        return $this->cachedAssignments ??= $this->roleAssignments()
            ->whereNull('revoked_at')
            ->with(['role', 'role.permissions'])
            ->get();
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
     * Return merged permission slugs from all active roles.
     *
     * @return array<string>
     */
    public function activePermissions(): array
    {
        $slugs = [];

        foreach ($this->activeAssignments() as $assignment) {
            /** @var Role|null $role */
            $role = $assignment->role;

            if ($role === null) {
                continue;
            }

            foreach ($role->permissions as $permission) {
                $slugs[$permission->slug] = true;
            }
        }

        return array_keys($slugs);
    }

    /**
     * Return true if the user has the given permission slug in their merged active permissions.
     */
    public function hasPermissionTo(string $slug): bool
    {
        return in_array($slug, $this->activePermissions(), true);
    }

    /**
     * Return the lowest (most privileged) hierarchy_level across all active roles.
     * Returns PHP_INT_MAX when the user has no active roles (no privileges).
     */
    public function lowestHierarchyLevel(): int
    {
        $levels = $this->activeAssignments()
            ->map(fn (UserRoleAssignment $a) => $a->role)
            ->filter()
            ->map(fn (Role $role) => $role->hierarchy_level)
            ->all();

        return $levels !== [] ? (int) min($levels) : PHP_INT_MAX;
    }
}
