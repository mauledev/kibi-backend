<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * UserRoleAssignment Eloquent Model — Infrastructure only.
 *
 * @property int $id
 * @property int $user_id
 * @property int $role_id
 * @property int|null $school_id
 * @property int|null $assigned_by
 * @property Carbon $assigned_at
 * @property Carbon|null $revoked_at
 */
class UserRoleAssignment extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
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
}
