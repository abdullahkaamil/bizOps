<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to the central (SaaS administration) domain. If tenancy
 * has been initialized we are on a tenant subdomain, so the route 404s.
 */
class EnsureCentralDomain
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (tenancy()->initialized) {
            abort(404);
        }

        return $next($request);
    }
}
