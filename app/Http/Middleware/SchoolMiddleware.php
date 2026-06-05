<?php

namespace App\Http\Middleware;

use App\Common\School\SchoolContext;
use App\Common\Tenant\TenantContext;
use App\Http\Response\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SchoolMiddleware
{
    /**
     * Resolve the school from the X-School-Uuid request header and bind SchoolContext
     * into the container. When the header is absent (tenant-level request), does nothing.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $schoolUuid = $request->header('X-School-Uuid');

        if ($schoolUuid === null) {
            return $next($request);
        }

        $tenantId = app(TenantContext::class)->tenantId;

        $school = DB::table('schools')
            ->where('uuid', $schoolUuid)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->first(['id']);

        if ($school === null) {
            return ApiResponse::notFound('School not found');
        }

        app()->instance(SchoolContext::class, new SchoolContext(schoolId: $school->id));

        return $next($request);
    }
}
