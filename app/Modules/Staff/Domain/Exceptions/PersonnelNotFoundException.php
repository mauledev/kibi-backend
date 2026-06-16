<?php

namespace App\Modules\Staff\Domain\Exceptions;

use RuntimeException;

class PersonnelNotFoundException extends RuntimeException
{
    public function __construct(string $uuid)
    {
        parent::__construct("Staff member \"{$uuid}\" was not found.");
    }
}
