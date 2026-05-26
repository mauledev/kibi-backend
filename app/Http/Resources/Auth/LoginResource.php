<?php

namespace App\Http\Resources\Auth;

use App\Modules\Auth\Application\DTOs\LoginOutput;
use App\Modules\Roles\Domain\Entities\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LoginOutput $output */
        $output = $this->resource;

        return [
            'id' => $output->publicId,
            'email' => $output->email,
            'full_name' => $output->fullName,
            'is_staff' => $output->isStaff,
            'roles' => array_map(fn (Role $role) => [
                'id' => $role->getPublicId(),
                'name' => $role->getName(),
                'slug' => $role->getSlug(),
                'hierarchy_level' => $role->getHierarchyLevel(),
            ], $output->roles),
            'permissions' => $output->permissions,
            'token' => $output->token,
        ];
    }
}
