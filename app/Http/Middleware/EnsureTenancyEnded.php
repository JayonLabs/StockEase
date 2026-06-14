<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenancyEnded
{
    /**
     * End tenancy if initialized, ensuring queries run in central context.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        return $next($request);
    }
}
