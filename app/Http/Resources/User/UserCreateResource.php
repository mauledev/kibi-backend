<?php

namespace App\Http\Resources\User;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * UserCreateResource
 * Serializa User después de crear
 * Incluye todos los campos necesarios post-creación
 */
class UserCreateResource extends JsonResource
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
        ];
    }
}
