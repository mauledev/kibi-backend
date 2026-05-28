<?php

namespace App\Http\Resources\User;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * UserListResource
 * Serializes User for list responses (minimal fields).
 */
class UserListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'uuid' => $user->uuid,
            'email' => $user->email,
            'full_name' => $user->full_name,
            'status' => $user->status,
        ];
    }
}
