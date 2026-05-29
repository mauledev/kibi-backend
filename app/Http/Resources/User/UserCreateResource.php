<?php

namespace App\Http\Resources\User;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * UserCreateResource
 * Serializes User after creation.
 */
class UserCreateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'uuid' => $user->uuid,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name_paternal' => $user->last_name_paternal,
            'last_name_maternal' => $user->last_name_maternal,
            'full_name' => $user->first_name.' '.$user->last_name_paternal.($user->last_name_maternal !== null ? ' '.$user->last_name_maternal : ''),
            'status' => $user->status,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
