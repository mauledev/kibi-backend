<?php

namespace App\Http\Resources\User;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * UserDetailResource
 * Serializa User con detalles completos
 * Más campos que UserResource para listar
 */
class UserDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->role,
            'school_id' => $user->school_id,
            'status' => $user->status,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }
}
