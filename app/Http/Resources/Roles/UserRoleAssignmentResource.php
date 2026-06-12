<?php

namespace App\Http\Resources\Roles;

use App\Modules\Roles\Domain\Entities\UserRoleAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserRoleAssignment
 */
class UserRoleAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var UserRoleAssignment $assignment */
        $assignment = $this->resource;

        return [
            'uuid' => $assignment->getUuid(),
            'assigned_at' => $assignment->getAssignedAt()->format('c'),
            'revoked_at' => $assignment->getRevokedAt()?->format('c'),
            'is_active' => $assignment->isActive(),
        ];
    }
}
