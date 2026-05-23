<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class School extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'public_id',
        'tenant_id',
        'name',
        'slug',
        'address',
        'phone',
        'status',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'address' => 'array',
    ];

    /** @return BelongsTo<Tenant, covariant School> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
