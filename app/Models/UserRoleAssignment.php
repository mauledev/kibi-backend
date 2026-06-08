<?php

namespace App\Models;

use Database\Factories\UserRoleAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * UserRoleAssignment Eloquent Model — Infrastructure only.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $role_id
 * @property int|null $school_id
 * @property int|null $assigned_by
 * @property Carbon $assigned_at
 * @property Carbon|null $revoked_at
 */
class UserRoleAssignment extends Model
{
    /** @use HasFactory<UserRoleAssignmentFactory> */
    use HasFactory;

    protected static function booting(): void
    {
        static::creating(function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'user_id',
        'role_id',
        'school_id',
        'assigned_by',
        'assigned_at',
        'revoked_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'role_id' => 'integer',
        'school_id' => 'integer',
        'assigned_by' => 'integer',
        'assigned_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** @return BelongsTo<User, covariant UserRoleAssignment> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Role, covariant UserRoleAssignment> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return BelongsTo<School, covariant UserRoleAssignment> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return HasMany<UserRoleAssignmentDenial, $this> */
    public function denials(): HasMany
    {
        return $this->hasMany(UserRoleAssignmentDenial::class, 'role_user_assignment_id');
    }
}
