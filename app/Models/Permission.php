<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Permission Eloquent Model — Infrastructure only, never leaves the repository layer.
 *
 * @property int $id
 * @property string $uuid
 * @property int $category_id
 * @property string $name
 * @property string $slug
 */
class Permission extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'category_id',
        'name',
        'slug',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'created_at' => 'datetime',
    ];

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    /** @return BelongsTo<PermissionCategory, covariant Permission> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PermissionCategory::class);
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
