<?php

namespace App\Modules\Staff\Domain\Contracts;

use App\Modules\Staff\Domain\Entities\SuperadminApprovalRequest;
use App\Modules\Staff\Domain\Enums\SuperadminApprovalStatusEnum;
use DateTimeImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

interface SuperadminApprovalRepositoryInterface
{
    /**
     * Persist a new pending request with the candidate snapshot.
     *
     * @throws UniqueConstraintViolationException When a live
     *                                            pending request already exists for the candidate email (partial unique index).
     */
    public function create(
        int $proposedBy,
        string $justification,
        string $candidateEmail,
        string $candidateFirstName,
        string $candidateLastNamePaternal,
        ?string $candidateLastNameMaternal,
        ?string $candidatePhone,
        DateTimeImmutable $expiresAt,
    ): SuperadminApprovalRequest;

    public function findByUuid(string $uuid): ?SuperadminApprovalRequest;

    /**
     * SELECT ... FOR UPDATE variant — call only inside a transaction. Guards the
     * approve/reject race: two resolvers locking the same row serialize, and the
     * second sees the already-transitioned status.
     */
    public function findByUuidForUpdate(string $uuid): ?SuperadminApprovalRequest;

    /** The (at most one, index-enforced) pending request for this candidate email. */
    public function findPendingByEmail(string $candidateEmail): ?SuperadminApprovalRequest;

    public function markApproved(int $id, int $resolvedBy, int $createdUserId): void;

    public function markRejected(int $id, int $resolvedBy, string $reason): void;

    public function markExpired(int $id): void;

    /**
     * @return array{items: array<SuperadminApprovalRequest>, total: int, per_page: int, current_page: int, last_page: int}
     */
    public function list(int $page, int $perPage, ?SuperadminApprovalStatusEnum $status = null): array;
}
