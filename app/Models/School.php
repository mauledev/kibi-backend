<?php

namespace App\Models;

use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'uuid',
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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /** @return BelongsTo<Tenant, covariant School> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
