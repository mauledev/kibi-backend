<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ValidateSignature as Middleware;

/**
 * ValidateSignature
 * Alias de middleware para URLs firmadas
 */
class ValidateSignature extends Middleware
{
    /**
     * @var array<int, string>
     */
    protected $except = [];
}
