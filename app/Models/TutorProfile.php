<?php

namespace App\Models;

use Database\Factories\TutorProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * TutorProfile Model — data access only, no business logic.
 *
 * One profile exists per tutor user. The user_id FK is unique.
 * Business logic lives in App\Modules\Tutor\Domain\Entities\Tutor.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string|null $occupation
 */
class TutorProfile extends Model
{
    /** @use HasFactory<TutorProfileFactory> */
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
        'occupation',
    ];

    /** @return BelongsTo<User, covariant TutorProfile> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
