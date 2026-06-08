<?php

namespace App\Modules\Staff\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when a requested permission is not part of the role's default
 * catalogue. Creation can only narrow a role's permissions (via denials),
 * never grant a permission outside the role's category bounds.
 */
class PermissionNotAllowedException extends RuntimeException
{
    public function __construct(string $permission, string $role)
    {
        parent::__construct("Permission \"{$permission}\" is not allowed for role \"{$role}\".");
    }
}
