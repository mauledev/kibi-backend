<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * UserListResource
 * Serializa User para listas (campos mínimos)
 * Optimizado para rendimiento en listas grandes
 */
class UserListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'role' => $this->role,
            'status' => $this->status,
        ];
    }
}
