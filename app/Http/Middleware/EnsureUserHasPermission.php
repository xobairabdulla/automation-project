<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $request->user()?->hasPermission($permission)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::error('You do not have the required permission.', 403);
            }

            abort(403, 'You do not have the required permission.');
        }

        return $next($request);
    }
}
