<?php

namespace App\Models;

use Database\Factories\StudentProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * StudentProfile Model — data access only, no business logic.
 *
 * One profile exists per student user. The user_id FK is unique.
 * Business logic lives in App\Modules\Student\Domain\Entities\Student.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string|null $birth_date
 * @property string|null $national_id
 * @property string|null $enrollment_number
 * @property string|null $gender
 * @property string|null $blood_type
 * @property int|null $group_id
 */
class StudentProfile extends Model
{
    /** @use HasFactory<StudentProfileFactory> */
    use HasFactory;

    use SoftDeletes;

    protected static function booting(): void
    {
        static::creating(function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    protected $fillable = [
        'uuid',
        'user_id',
        'birth_date',
        'national_id',
        'enrollment_number',
        'gender',
        'blood_type',
        'group_id',
    ];

    /** @return BelongsTo<User, covariant StudentProfile> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Group, covariant StudentProfile> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
