<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyFromUser
{
    /**
     * Handle an incoming request and initialize tenancy based on the authenticated user's company.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            if ($user->company_id) {
                $user->loadMissing('company');

                if ($user->company) {
                    tenancy()->initialize($user->company);
                }
            }
        }

        return $next($request);
    }
}
