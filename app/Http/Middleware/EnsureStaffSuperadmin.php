<?php

namespace App\Http\Middleware;

use App\Common\Audit\AuditLoggerInterface;
use App\Http\Response\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a staff route to Superadmin users.
 *
 * Staff routes never bind TenantContext, so the `Gate::before` owner bypass
 * does not apply and authority cannot be checked through the permission gate.
 * Superadmin authority is therefore verified explicitly here.
 *
 * Denied attempts are audited (SCRUM-520 AC: a non-superadmin probing a
 * privileged staff route leaves a trace, not just a 403).
 */
class EnsureStaffSuperadmin
{
    public function __construct(private readonly AuditLoggerInterface $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->is_staff || ! $user->hasRole('superadmin')) {
            $this->audit->log(
                action: 'staff.access_denied',
                userId: $user?->id,
                structAfter: [
                    'route' => $request->route()?->getName(),
                    'path' => $request->path(),
                    'method' => $request->method(),
                ],
            );

            return ApiResponse::forbidden('Only Superadmin can manage Backoffice personnel.');
        }

        return $next($request);
    }
}
