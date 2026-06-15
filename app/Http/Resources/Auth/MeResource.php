<?php

namespace App\Http\Resources\Auth;

use App\Modules\Auth\Application\DTOs\MeOutput;
use App\Modules\Roles\Domain\Entities\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MeOutput $output */
        $output = $this->resource;

        return [
            'id' => $output->uuid,
            'email' => $output->email,
            'first_name' => $output->firstName,
            'last_name_paternal' => $output->lastNamePaternal,
            'last_name_maternal' => $output->lastNameMaternal,
            'full_name' => $output->fullName,
            'is_staff' => $output->isStaff,
            'roles' => array_map(fn (Role $role) => [
                'uuid' => $role->getUuid(),
                'name' => $role->getName(),
                'slug' => $role->getSlug(),
                'hierarchy_level' => $role->getHierarchyLevel(),
            ], $output->roles),
            'permissions' => $output->permissions,
            'must_accept_policy' => $output->mustAcceptPolicy,
        ];
    }
}
