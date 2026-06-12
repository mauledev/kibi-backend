<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $proposed_by
 * @property string $justification
 * @property string $candidate_email
 * @property string $candidate_first_name
 * @property string $candidate_last_name_paternal
 * @property string|null $candidate_last_name_maternal
 * @property string|null $candidate_phone
 * @property string $status
 * @property Carbon $expires_at
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property string|null $rejection_reason
 * @property int|null $created_user_id
 */
class SuperadminApprovalRequest extends Model
{
    protected $fillable = [
        'uuid',
        'proposed_by',
        'justification',
        'candidate_email',
        'candidate_first_name',
        'candidate_last_name_paternal',
        'candidate_last_name_maternal',
        'candidate_phone',
        'status',
        'expires_at',
        'resolved_by',
        'resolved_at',
        'rejection_reason',
        'created_user_id',
    ];

    protected $casts = [
        'proposed_by' => 'integer',
        'resolved_by' => 'integer',
        'created_user_id' => 'integer',
        'expires_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    protected static function booting(): void
    {
        static::creating(function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<User, covariant SuperadminApprovalRequest> */
    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    /** @return BelongsTo<User, covariant SuperadminApprovalRequest> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** @return BelongsTo<User, covariant SuperadminApprovalRequest> */
    public function createdUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }
}
