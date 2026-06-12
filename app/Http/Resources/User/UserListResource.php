<?php

namespace App\Http\Resources\User;

use App\Modules\User\Domain\Entities\RoleAssignment;
use App\Modules\User\Domain\Entities\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a Domain User entity for the paginated list response.
 *
 * Exposes only the fields needed for a compact list view. Full detail
 * (first_name, last_name_* individually) is available from UserDetailResource.
 *
 * @mixin User
 */
class UserListResource extends JsonResource
{
    /**
     * Transform the Domain User entity into the list API response shape.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'uuid' => $user->getUuid(),
            'full_name' => $user->getFullName(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhone(),
            'status' => $user->getStatus(),
            'roles' => array_map(
                fn (RoleAssignment $role): array => [
                    'role_uuid' => $role->roleUuid,
                    'slug' => $role->slug,
                    'name' => $role->name,
                    'school_uuid' => $role->schoolUuid,
                ],
                $user->getRoles()
            ),
            'created_at' => $user->getCreatedAt()->format('c'),
        ];
    }
}
