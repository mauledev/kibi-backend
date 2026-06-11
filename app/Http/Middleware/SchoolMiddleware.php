<?php

namespace App\Http\Middleware;

use App\Common\School\SchoolContext;
use App\Http\Response\ApiResponse;
use App\Modules\Schools\Domain\Contracts\SchoolRepositoryInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SchoolMiddleware
{
    /**
     * Resolve the school from the X-School-Uuid request header and bind SchoolContext
     * into the container. When the header is absent (tenant-level request), does nothing.
     *
     * The repository is resolved lazily inside handle() — not via constructor injection —
     * because EloquentSchoolRepository depends on TenantContext, which is bound by
     * TenantMiddleware. Laravel instantiates all route middleware constructors before
     * running the pipeline, so constructor injection would attempt to resolve TenantContext
     * before TenantMiddleware::handle() has executed.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $schoolUuid = $request->header('X-School-Uuid');

        if ($schoolUuid === null) {
            return $next($request);
        }

        $school = app(SchoolRepositoryInterface::class)->findByUuid($schoolUuid);

        if ($school === null) {
            return ApiResponse::notFound('School not found');
        }

        app()->instance(SchoolContext::class, new SchoolContext(schoolId: $school->getId()));

        return $next($request);
    }
}
