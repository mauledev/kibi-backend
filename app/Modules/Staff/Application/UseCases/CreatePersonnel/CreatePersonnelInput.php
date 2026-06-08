<?php

namespace App\Modules\Staff\Application\UseCases\CreatePersonnel;

class CreatePersonnelInput
{
    /**
     * @param  array<string>  $permissions  Effective permission slugs the actor left enabled.
     */
    public function __construct(
        public readonly string $role,
        public readonly string $firstName,
        public readonly string $lastNamePaternal,
        public readonly ?string $lastNameMaternal,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly array $permissions,
        public readonly ?int $createdBy,
    ) {}
}
