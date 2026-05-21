<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * UserResource
 * Serializa User Entity/DTO a JSON
 * Controla qué campos se devuelven al cliente
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? $this->resource->id,
            'email' => $this->email ?? $this->resource->email,
            'name' => $this->name ?? $this->resource->name,
            'role' => $this->role ?? $this->resource->role,
            'school_id' => $this->schoolId ?? $this->resource->school_id,
            'status' => $this->status ?? $this->resource->status,
            'created_at' => $this->created_at ?? $this->resource->getCreatedAt()?->toIso8601String(),
        ];
    }
}
