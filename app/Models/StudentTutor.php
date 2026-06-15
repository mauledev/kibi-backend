<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * StudentTutor Model — pivot model for the student_tutors table.
 *
 * Represents the link between a tutor user and a student user.
 * A link is active when unlinked_at IS NULL.
 *
 * @property int $id
 * @property int $tutor_user_id
 * @property int $student_user_id
 * @property string|null $relationship
 * @property string $linked_at
 * @property string|null $unlinked_at
 */
class StudentTutor extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tutor_user_id',
        'student_user_id',
        'relationship',
        'linked_at',
        'unlinked_at',
    ];

    protected $table = 'student_tutors';

    /** @return BelongsTo<User, covariant StudentTutor> */
    public function tutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tutor_user_id');
    }

    /** @return BelongsTo<User, covariant StudentTutor> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_user_id');
    }
}
