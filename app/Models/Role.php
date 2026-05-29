<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Role Eloquent Model — Infrastructure only, never leaves the repository layer.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $tenant_id
 * @property string $name
 * @property string $slug
 * @property int $hierarchy_level
 * @property bool $is_system_role
 */
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory, SoftDeletes;

    protected static function booting(): void
    {
        static::creating(function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'slug',
        'hierarchy_level',
        'is_system_role',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'hierarchy_level' => 'integer',
        'is_system_role' => 'boolean',
        'created_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    /** @return BelongsTo<Tenant, covariant Role> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }
}
