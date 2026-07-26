<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to tenant (subdomain) context. If tenancy has not been
 * initialized we are on the central domain, so the route 404s.
 */
class EnsureTenantDomain
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! tenancy()->initialized) {
            abort(404);
        }

        return $next($request);
    }
}
