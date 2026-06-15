<?php

namespace App\Http\Resources\Treasury;

use App\Modules\Treasury\Domain\Entities\Payment;
use App\Modules\Treasury\Domain\Entities\PaymentStateTransition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for the payment detail endpoint.
 *
 * Wraps a Payment entity together with its state log — the two pieces the
 * frontend renders in the payment detail drawer for the MVP scope.
 */
class PaymentDetailResource extends JsonResource
{
    /**
     * @param  array{
     *     payment: Payment,
     *     stateLog: array<PaymentStateTransition>,
     * }  $resource
     */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /**
         * @var array{
         *     payment: Payment,
         *     stateLog: array<PaymentStateTransition>,
         * } $data
         */
        $data = $this->resource;

        $summary = (new PaymentSummaryResource($data['payment']))->toArray($request);

        return array_merge($summary, [
            'state_log' => PaymentStateTransitionResource::collection($data['stateLog'])->resolve(),
        ]);
    }
}
