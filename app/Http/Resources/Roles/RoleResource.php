<?php

namespace App\Http\Resources\Roles;

use App\Modules\Roles\Domain\Entities\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Role $role */
        $role = $this->resource;

        return [
            'uuid' => $role->getUuid(),
            'name' => $role->getName(),
            'slug' => $role->getSlug(),
            'hierarchy_level' => $role->getHierarchyLevel(),
            'is_system_role' => $role->isSystemRole(),
            'permissions' => PermissionResource::collection($role->getPermissions()),
            'created_at' => $role->getCreatedAt()->format('c'),
        ];
    }
}
