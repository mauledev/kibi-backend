<?php

namespace App\Http\Resources\User;

use App\Modules\User\Domain\Entities\RoleAssignment;
use App\Modules\User\Domain\Entities\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a Domain User entity for the single-user detail response.
 *
 * Includes all fields from UserListResource plus the individual name components
 * (first_name, last_name_paternal, last_name_maternal) required by official
 * school documents (boletas, CFDI).
 *
 * @mixin User
 */
class UserDetailResource extends JsonResource
{
    /**
     * Transform the Domain User entity into the full detail API response shape.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'uuid' => $user->getUuid(),
            'first_name' => $user->getFirstName(),
            'last_name_paternal' => $user->getLastNamePaternal(),
            'last_name_maternal' => $user->getLastNameMaternal(),
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
