<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->canAccessApplication()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::error('This account is not allowed to access the application.', 403);
            }

            abort(403, 'This account is not allowed to access the application.');
        }

        return $next($request);
    }
}
