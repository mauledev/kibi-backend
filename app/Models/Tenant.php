<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'public_id',
        'name',
        'slug',
        'legal_name',
        'rfc',
        'fiscal_address',
        'contact_email',
        'contact_phone',
        'status',
    ];

    protected $casts = [
        'fiscal_address' => 'array',
    ];

    /** @return HasMany<School, $this> */
    public function schools(): HasMany
    {
        return $this->hasMany(School::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
