<?php

namespace App\Http\Resources\Staff;

use App\Modules\Staff\Domain\Entities\ApprovalParticipant;
use App\Modules\Staff\Domain\Entities\SuperadminApprovalRequest;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a superadmin approval request. Only uuids are exposed (never
 * internal ids), and `status` is the EFFECTIVE status: a pending row past its
 * expiry reads as `expired` without the database being written.
 */
class SuperadminApprovalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SuperadminApprovalRequest $approval */
        $approval = $this->resource;

        return [
            'id' => $approval->getUuid(),
            'status' => $approval->getEffectiveStatus(new DateTimeImmutable)->value,
            'justification' => $approval->getJustification(),
            'personal_data' => [
                'first_name' => $approval->getCandidateFirstName(),
                'last_name_paternal' => $approval->getCandidateLastNamePaternal(),
                'last_name_maternal' => $approval->getCandidateLastNameMaternal(),
                'email' => $approval->getCandidateEmail(),
                'phone' => $approval->getCandidatePhone(),
            ],
            'proposed_by' => $this->participant($approval->getProposedBy()),
            'resolved_by' => $approval->getResolvedBy() !== null
                ? $this->participant($approval->getResolvedBy())
                : null,
            'resolved_at' => $approval->getResolvedAt()?->format(DateTimeInterface::ATOM),
            'rejection_reason' => $approval->getRejectionReason(),
            'created_user_id' => $approval->getCreatedUserUuid(),
            'expires_at' => $approval->getExpiresAt()->format(DateTimeInterface::ATOM),
            'created_at' => $approval->getCreatedAt()->format(DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, string> */
    private function participant(ApprovalParticipant $participant): array
    {
        return [
            'id' => $participant->getUuid(),
            'full_name' => $participant->getFullName(),
            'email' => $participant->getEmail(),
        ];
    }
}
