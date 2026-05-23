<?php

declare(strict_types=1);

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
            'id' => $permission->getPublicId(),
            'name' => $permission->getName(),
            'slug' => $permission->getSlug(),
            'created_at' => $permission->getCreatedAt()->format('c'),
        ];
    }
}
