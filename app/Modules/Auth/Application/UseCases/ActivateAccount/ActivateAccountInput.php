<?php

namespace App\Modules\Auth\Application\UseCases\ActivateAccount;

class ActivateAccountInput
{
    public function __construct(
        public readonly string $userUuid,
        public readonly string $password,
    ) {}
}
