<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * User Model
 * Solo acceso a BD, no contiene lógica de negocio
 * La lógica está en la Entity del dominio
 */
class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'id',
        'email',
        'name',
        'password',
        'role',
        'school_id',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
