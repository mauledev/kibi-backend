<?php

namespace App\Modules\Staff\Domain\Exceptions;

use RuntimeException;

class InvalidStaffRoleException extends RuntimeException
{
    public function __construct(string $role)
    {
        parent::__construct("Staff role \"{$role}\" is not valid.");
    }
}
