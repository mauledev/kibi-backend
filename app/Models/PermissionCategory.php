<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PermissionCategory Eloquent Model — Infrastructure only.
 *
 * @property int $id
 * @property string $public_id
 * @property int|null $school_id
 * @property string $name
 */
class PermissionCategory extends Model
{
    use HasFactory, SoftDeletes;

    public $timestamps = false;

    protected $fillable = [
        'public_id',
        'school_id',
        'name',
    ];

    protected $casts = [
        'school_id' => 'integer',
        'created_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    const CREATED_AT = 'created_at';

    const UPDATED_AT = null;

    /** @return BelongsTo<School, covariant PermissionCategory> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return HasMany<Permission, $this> */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'category_id');
    }
}
