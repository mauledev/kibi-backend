<?php

namespace App\Http\Resources\Roles;

use App\Modules\Roles\Domain\Entities\Permission;
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
            'bypasses_permissions' => $role->bypassesPermissions(),
            'requires_2fa' => $role->requiresTwoFactor(),
            'permissions' => $this->resolvePermissions($role),
            'created_at' => $role->getCreatedAt()->format('c'),
        ];
    }

    /**
     * Resolve the permissions array for the role.
     *
     * When availablePermissions is populated (detail/show context), returns all applicable
     * permissions with a granted: true/false flag so the frontend can render the edit-permissions
     * view. When empty (list/index context), returns only the granted permissions without a flag,
     * preserving the existing list shape.
     *
     * @return array<mixed>
     */
    private function resolvePermissions(Role $role): array
    {
        $available = $role->getAvailablePermissions();

        if (empty($available)) {
            return PermissionResource::collection($role->getPermissions())->resolve();
        }

        $grantedSlugs = array_map(fn (Permission $p) => $p->getSlug(), $role->getPermissions());

        return array_map(fn (Permission $p) => [
            'uuid' => $p->getUuid(),
            'name' => $p->getName(),
            'slug' => $p->getSlug(),
            'granted' => in_array($p->getSlug(), $grantedSlugs, true),
            'created_at' => $p->getCreatedAt()->format('c'),
        ], $available);
    }
}
