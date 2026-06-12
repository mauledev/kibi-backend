<?php

namespace App\Models;

use Database\Factories\PermissionCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * PermissionCategory Eloquent Model — Infrastructure only.
 *
 * @property int $id
 * @property string $uuid
 * @property string $scope
 * @property string $name
 */
class PermissionCategory extends Model
{
    /** @use HasFactory<PermissionCategoryFactory> */
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
        'scope',
        'name',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    /** @return HasMany<Permission, $this> */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'category_id');
    }
}
