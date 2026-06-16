<?php

namespace App\Modules\Staff\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when a valid staff role slug has no seeded role row. Signals a
 * misconfigured environment (RolesAndPermissionsSeeder not run), not bad input.
 */
class StaffRoleNotFoundException extends RuntimeException
{
    public function __construct(string $slug)
    {
        parent::__construct("Staff role \"{$slug}\" is not seeded in the database.");
    }
}
