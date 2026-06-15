<?php

namespace App\Http\Middleware;

use App\Common\Staff\StaffContext;
use App\Http\Response\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffMiddleware
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->is_staff) {
            return ApiResponse::forbidden('Access restricted to staff users');
        }

        app()->instance(StaffContext::class, new StaffContext);

        return $next($request);
    }
}
