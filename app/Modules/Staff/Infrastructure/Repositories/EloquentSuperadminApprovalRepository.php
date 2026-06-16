<?php

namespace App\Modules\Staff\Infrastructure\Repositories;

use App\Models\SuperadminApprovalRequest as SuperadminApprovalRequestModel;
use App\Models\User as UserModel;
use App\Modules\Staff\Domain\Contracts\SuperadminApprovalRepositoryInterface;
use App\Modules\Staff\Domain\Entities\ApprovalParticipant;
use App\Modules\Staff\Domain\Entities\SuperadminApprovalRequest;
use App\Modules\Staff\Domain\Enums\SuperadminApprovalStatusEnum;
use DateTimeImmutable;

class EloquentSuperadminApprovalRepository implements SuperadminApprovalRepositoryInterface
{
    private const RELATIONS = ['proposer', 'resolver', 'createdUser'];

    /** {@inheritDoc} */
    public function create(
        int $proposedBy,
        string $justification,
        string $candidateEmail,
        string $candidateFirstName,
        string $candidateLastNamePaternal,
        ?string $candidateLastNameMaternal,
        ?string $candidatePhone,
        DateTimeImmutable $expiresAt,
    ): SuperadminApprovalRequest {
        $model = SuperadminApprovalRequestModel::create([
            'proposed_by' => $proposedBy,
            'justification' => $justification,
            'candidate_email' => $candidateEmail,
            'candidate_first_name' => $candidateFirstName,
            'candidate_last_name_paternal' => $candidateLastNamePaternal,
            'candidate_last_name_maternal' => $candidateLastNameMaternal,
            'candidate_phone' => $candidatePhone,
            'status' => SuperadminApprovalStatusEnum::PENDING_APPROVAL->value,
            'expires_at' => $expiresAt,
        ]);

        return $this->toDomain($model->load(self::RELATIONS));
    }

    /** {@inheritDoc} */
    public function findByUuid(string $uuid): ?SuperadminApprovalRequest
    {
        $model = SuperadminApprovalRequestModel::with(self::RELATIONS)
            ->where('uuid', $uuid)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function findByUuidForUpdate(string $uuid): ?SuperadminApprovalRequest
    {
        $model = SuperadminApprovalRequestModel::where('uuid', $uuid)
            ->lockForUpdate()
            ->first();

        return $model !== null ? $this->toDomain($model->load(self::RELATIONS)) : null;
    }

    /** {@inheritDoc} */
    public function findPendingByEmail(string $candidateEmail): ?SuperadminApprovalRequest
    {
        $model = SuperadminApprovalRequestModel::with(self::RELATIONS)
            ->where('candidate_email', $candidateEmail)
            ->where('status', SuperadminApprovalStatusEnum::PENDING_APPROVAL->value)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    /** {@inheritDoc} */
    public function markApproved(int $id, int $resolvedBy, int $createdUserId): void
    {
        SuperadminApprovalRequestModel::whereKey($id)->update([
            'status' => SuperadminApprovalStatusEnum::APPROVED->value,
            'resolved_by' => $resolvedBy,
            'resolved_at' => now(),
            'created_user_id' => $createdUserId,
        ]);
    }

    /** {@inheritDoc} */
    public function markRejected(int $id, int $resolvedBy, string $reason): void
    {
        SuperadminApprovalRequestModel::whereKey($id)->update([
            'status' => SuperadminApprovalStatusEnum::REJECTED->value,
            'resolved_by' => $resolvedBy,
            'resolved_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /** {@inheritDoc} */
    public function markExpired(int $id): void
    {
        SuperadminApprovalRequestModel::whereKey($id)->update([
            'status' => SuperadminApprovalStatusEnum::EXPIRED->value,
            'resolved_at' => now(),
        ]);
    }

    /** {@inheritDoc} */
    public function list(int $page, int $perPage, ?SuperadminApprovalStatusEnum $status = null): array
    {
        $paginator = SuperadminApprovalRequestModel::with(self::RELATIONS)
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => array_map(
                fn (SuperadminApprovalRequestModel $model) => $this->toDomain($model),
                $paginator->items(),
            ),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    private function toDomain(SuperadminApprovalRequestModel $model): SuperadminApprovalRequest
    {
        $proposer = $model->proposer;

        // proposed_by is a NOT NULL constrained FK; a missing row is a data integrity bug.
        if ($proposer === null) {
            throw new \RuntimeException("Approval request {$model->id} has no proposer row.");
        }

        return new SuperadminApprovalRequest(
            id: $model->id,
            uuid: $model->uuid,
            status: SuperadminApprovalStatusEnum::from($model->status),
            justification: $model->justification,
            candidateEmail: $model->candidate_email,
            candidateFirstName: $model->candidate_first_name,
            candidateLastNamePaternal: $model->candidate_last_name_paternal,
            candidateLastNameMaternal: $model->candidate_last_name_maternal,
            candidatePhone: $model->candidate_phone,
            proposedBy: $this->toParticipant($proposer),
            resolvedBy: $model->resolver !== null ? $this->toParticipant($model->resolver) : null,
            resolvedAt: $this->toImmutable($model->resolved_at?->toIso8601String()),
            rejectionReason: $model->rejection_reason,
            createdUserUuid: $model->createdUser?->uuid,
            expiresAt: new DateTimeImmutable($model->expires_at->toIso8601String()),
            createdAt: new DateTimeImmutable($model->created_at?->toIso8601String() ?? 'now'),
        );
    }

    private function toParticipant(UserModel $user): ApprovalParticipant
    {
        $fullName = "{$user->first_name} {$user->last_name_paternal}";

        if ($user->last_name_maternal !== null) {
            $fullName .= " {$user->last_name_maternal}";
        }

        return new ApprovalParticipant(
            id: $user->id,
            uuid: $user->uuid,
            fullName: $fullName,
            email: $user->email,
        );
    }

    private function toImmutable(?string $iso): ?DateTimeImmutable
    {
        return $iso !== null ? new DateTimeImmutable($iso) : null;
    }
}
