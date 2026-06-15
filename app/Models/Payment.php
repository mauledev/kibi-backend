<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
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
        'tenant_id',
        'school_id',
        'created_by',
        'status',
        'payer_name',
        'reference',
        'amount_cents',
        'received_amount_cents',
        'currency',
        'paid_at',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'school_id' => 'integer',
        'created_by' => 'integer',
        'amount_cents' => 'integer',
        'received_amount_cents' => 'integer',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /** @return BelongsTo<Tenant, covariant Payment> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<School, covariant Payment> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return HasMany<PaymentStateTransition, covariant Payment> */
    public function stateTransitions(): HasMany
    {
        return $this->hasMany(PaymentStateTransition::class);
    }
}
