<?php

namespace App\Http\Middleware;

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
 */
class EnsureStaffSuperadmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->is_staff || ! $user->hasRole('superadmin')) {
            return ApiResponse::forbidden('Only Superadmin can manage Backoffice personnel.');
        }

        return $next($request);
    }
}
