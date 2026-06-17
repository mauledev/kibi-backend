<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes the directory stats counts for `GET /users/stats`.
 *
 * `total`   — users in the directory scope (role + school + authority).
 * `pending` — of those, the ones with an unverified email (invited, not activated).
 */
class UserStatsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{total: int, pending: int} $stats */
        $stats = $this->resource;

        return [
            'total' => $stats['total'],
            'pending' => $stats['pending'],
        ];
    }
}
