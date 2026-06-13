<?php

namespace App\Http\Middleware;

use App\Http\Response\ApiResponse;
use App\Modules\Auth\Application\Services\PolicyAcceptanceChecker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks app endpoints until the user has accepted the Responsible Use Policy
 * (SCRUM-520). Sits behind `auth:sanctum`; the policy-acceptance, /me and logout
 * routes are intentionally OUTSIDE this gate so a user who still owes acceptance
 * can read their session and accept.
 *
 * Users whose roles do not require the policy pass through untouched.
 */
class EnsurePolicyAccepted
{
    public function __construct(private readonly PolicyAcceptanceChecker $checker) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        /** @var array<string> $slugs */
        $slugs = $user->activeRoles()->pluck('slug')->all();

        if ($this->checker->mustAccept((int) $user->id, $slugs)) {
            return ApiResponse::error(
                'You must accept the Responsible Use Policy first.',
                403,
                ['policy' => ['ACCEPTANCE_REQUIRED']],
            );
        }

        return $next($request);
    }
}
