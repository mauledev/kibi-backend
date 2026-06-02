<?php

namespace App\Modules\Auth\Application\UseCases\Logout;

use App\Common\Audit\AuditLoggerInterface;
use App\Modules\Auth\Application\DTOs\LogoutInput;
use App\Modules\Auth\Domain\Contracts\TokenServiceInterface;

class LogoutUseCase
{
    public function __construct(
        private readonly TokenServiceInterface $tokens,
        private readonly AuditLoggerInterface $audit,
    ) {}

    public function execute(LogoutInput $input): void
    {
        $this->tokens->revokeById($input->tokenId);

        $this->audit->log(
            action: 'auth.logout',
            userId: $input->userId,
            tenantId: $input->tenantId,
            structAfter: ['token_id' => $input->tokenId],
        );
    }
}
