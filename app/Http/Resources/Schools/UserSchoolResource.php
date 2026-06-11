<?php

namespace App\Http\Resources\Schools;

use App\Modules\Schools\Domain\Entities\School;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact school shape for `GET /me/schools` — what the client `SchoolGate` /
 * school switcher need. `id` is the public uuid (the internal id is never exposed).
 *
 * @mixin School
 */
class UserSchoolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $school = $this->resource;

        return [
            'id' => $school->getUuid(),
            'slug' => $school->getSlug(),
            'name' => $school->getName(),
            'logo_url' => null,
        ];
    }
}
