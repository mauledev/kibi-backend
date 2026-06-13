<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $policy_type
 * @property string $version
 * @property Carbon $accepted_at
 * @property string|null $ip
 */
class UserPolicyAcceptance extends Model
{
    protected $fillable = [
        'user_id',
        'policy_type',
        'version',
        'accepted_at',
        'ip',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'accepted_at' => 'datetime',
    ];

    /** @return BelongsTo<User, covariant UserPolicyAcceptance> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
