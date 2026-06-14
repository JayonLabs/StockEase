<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request and check the user has one of the required roles.
     *
     * @param  array<int, string>  $roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $roles = array_map('trim', $roles);

        if (! Auth::check()) {
            abort(403, 'Unauthorized access.');
        }

        $user = Auth::user();

        // platform_owner can access any route
        if ($user->hasRole(Role::PlatformOwner->value)) {
            return $next($request);
        }

        // super_admin can bypass unless route requires platform_owner
        if ($user->hasRole(Role::SuperAdmin->value)) {
            if (in_array(Role::PlatformOwner->value, $roles, true)) {
                abort(403, 'Unauthorized access.');
            }

            return $next($request);
        }

        if (! $user->hasAnyRole($roles)) {
            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }
}
