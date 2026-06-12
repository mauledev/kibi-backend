<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingStepStatus extends Model
{
    protected $table = 'onboarding_step_status';

    /**
     * Composite primary key — (progress_id, step).
     * Incrementing is disabled since the PK is not a single auto-increment column.
     * Laravel does not natively support composite PKs; we declare the first column
     * here so that Eloquent does not error on save, while composite uniqueness is
     * enforced at the DB level via the migration PRIMARY KEY constraint.
     *
     * @var string
     */
    protected $primaryKey = 'progress_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'progress_id',
        'step',
        'name',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    /** @return BelongsTo<OnboardingProgress, $this> */
    public function progress(): BelongsTo
    {
        return $this->belongsTo(OnboardingProgress::class, 'progress_id');
    }
}
