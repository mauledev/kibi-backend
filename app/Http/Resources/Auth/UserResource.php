<?php

namespace App\Http\Resources\Auth;

use App\Modules\Auth\Application\DTOs\LoginOutput;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * UserResource
 * Serializa User Entity/DTO a JSON
 * Controla qué campos se devuelven al cliente
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var LoginOutput $output */
        $output = $this->resource;

        return [
            'id' => $output->id,
            'email' => $output->email,
            'name' => $output->name,
            'role' => $output->role,
            'school_id' => $output->schoolId,
        ];
    }
}
