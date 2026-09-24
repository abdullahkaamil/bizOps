<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks access to a tenant whose license has expired, redirecting every
 * request to the "license expired" notice. Runs on every web request but only
 * enforces when tenancy has been initialized (i.e. on a tenant subdomain).
 */
class EnsureTenantLicenseIsActive
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! tenancy()->initialized) {
            return $next($request);
        }

        $tenant = tenant();

        // Accessible = status is active AND the license has not expired.
        if (! $tenant instanceof Tenant || $tenant->isAccessible()) {
            return $next($request);
        }

        // Allow the expired notice itself (and its assets) so we don't loop.
        if ($request->routeIs('tenant.license.expired') || $request->is('build/*')) {
            return $next($request);
        }

        return redirect()->route('tenant.license.expired');
    }
}
