<?php

namespace App\Models;

use Database\Factories\PaymentStateTransitionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentStateTransition extends Model
{
    /** @use HasFactory<PaymentStateTransitionFactory> */
    use HasFactory;

    // Append-only: only created_at exists on this table.
    public $timestamps = false;

    protected $fillable = [
        'payment_id',
        'event',
        'from_status',
        'to_status',
        'actor_user_id',
        'actor_name',
        'reason',
        'note',
        'created_at',
    ];

    protected $casts = [
        'payment_id' => 'integer',
        'actor_user_id' => 'integer',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Payment, covariant PaymentStateTransition> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<User, covariant PaymentStateTransition> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
