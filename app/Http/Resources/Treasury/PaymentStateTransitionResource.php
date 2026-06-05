<?php

namespace App\Http\Resources\Treasury;

use App\Modules\Treasury\Domain\Entities\PaymentStateTransition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentStateTransition
 */
class PaymentStateTransitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PaymentStateTransition $transition */
        $transition = $this->resource;

        return [
            'event' => $transition->getEvent()->value,
            'from_status' => $transition->getFromStatus()?->value,
            'to_status' => $transition->getToStatus()->value,
            'actor_name' => $transition->getActorName(),
            'reason' => $transition->getReason()?->value,
            'note' => $transition->getNote(),
            'created_at' => $transition->getCreatedAt()->format('c'),
        ];
    }
}
