<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $timezone
 * @property array<string> $days
 * @property string $start_time
 * @property string $end_time
 */
class StaffWorkSchedule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'timezone',
        'days',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'days' => 'array',
    ];

    protected static function booting(): void
    {
        static::creating(function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
        });
    }
}
