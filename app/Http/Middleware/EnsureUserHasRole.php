<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $normalizedRoles = collect($roles)
            ->flatMap(fn (string $role) => explode('|', $role))
            ->map(fn (string $role) => trim($role))
            ->filter()
            ->values()
            ->all();

        if (! $request->user()?->hasRole($normalizedRoles)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::error('You do not have the required role.', 403);
            }

            abort(403, 'You do not have the required role.');
        }

        return $next($request);
    }
}
