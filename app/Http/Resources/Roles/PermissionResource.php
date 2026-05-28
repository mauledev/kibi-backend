<?php

namespace App\Http\Resources\Roles;

use App\Modules\Roles\Domain\Entities\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Permission
 */
class PermissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Permission $permission */
        $permission = $this->resource;

        return [
            'uuid' => $permission->getUuid(),
            'name' => $permission->getName(),
            'slug' => $permission->getSlug(),
            'created_at' => $permission->getCreatedAt()->format('c'),
        ];
    }
}
