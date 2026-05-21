<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * User Model
 * Solo acceso a BD, no contiene lógica de negocio
 * La lógica está en la Entity del dominio
 *
 * @property int $id
 * @property string $email
 * @property string $name
 * @property string $password
 * @property string $role
 * @property string $school_id
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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
