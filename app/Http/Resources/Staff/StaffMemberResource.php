<?php

namespace App\Http\Resources\Staff;

use App\Modules\Staff\Domain\Entities\StaffMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffMemberResource extends JsonResource
{
    /**
     * Transform the StaffMember entity into the API response shape expected by
     * the frontend `backoffice-staff` mapper (snake_case).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var StaffMember $member */
        $member = $this->resource;

        return [
            'id' => $member->getUuid(),
            'role' => $member->getRole(),
            'personal_data' => [
                'first_name' => $member->getFirstName(),
                'last_name_paternal' => $member->getLastNamePaternal(),
                'last_name_maternal' => $member->getLastNameMaternal(),
                'email' => $member->getEmail(),
                'phone' => $member->getPhone(),
            ],
            'permissions' => $member->getPermissions(),
            'requires_2fa' => $member->requires2fa(),
            'created_at' => $member->getCreatedAt(),
        ];
    }
}
