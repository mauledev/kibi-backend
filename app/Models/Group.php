<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Group Model — data access only, no business logic.
 *
 * A group belongs to a grade and represents a classroom section (A, B, C, etc.).
 *
 * @property int $id
 * @property string $uuid
 * @property int $grade_id
 * @property string $name
 */
class Group extends Model
{
    use SoftDeletes;

    protected static function booting(): void
    {
        static::creating(function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    protected $fillable = [
        'uuid',
        'grade_id',
        'name',
    ];
}
