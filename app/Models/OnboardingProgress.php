<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OnboardingProgress extends Model
{
    protected static function booting(): void
    {
        static::creating(function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    protected $table = 'onboarding_progress';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'current_step',
        'status',
        'grace_period_ends_at',
    ];

    protected $casts = [
        'grace_period_ends_at' => 'datetime',
    ];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<OnboardingStepStatus, $this> */
    public function stepStatuses(): HasMany
    {
        return $this->hasMany(OnboardingStepStatus::class, 'progress_id');
    }
}
