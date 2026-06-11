<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UserRoleAssignmentDenial Eloquent Model — Infrastructure only.
 * Represents a permission subtracted from a specific user_role_assignment.
 *
 * @property int $id
 * @property int $role_user_assignment_id
 * @property int $permission_id
 */
class UserRoleAssignmentDenial extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'role_user_assignment_id',
        'permission_id',
    ];

    protected $casts = [
        'role_user_assignment_id' => 'integer',
        'permission_id' => 'integer',
    ];

    /** @return BelongsTo<UserRoleAssignment, covariant UserRoleAssignmentDenial> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(UserRoleAssignment::class, 'role_user_assignment_id');
    }

    /** @return BelongsTo<Permission, covariant UserRoleAssignmentDenial> */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
