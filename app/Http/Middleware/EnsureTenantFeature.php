<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route behind a tenant feature flag: 403 when the current tenant has the
 * named feature disabled. Flags default to ON (see Tenant::hasFeature), so the
 * gate is transparent until an operator turns a feature off for a tenant.
 */
class EnsureTenantFeature
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = tenancy()->initialized ? tenant() : null;

        if ($tenant instanceof Tenant && ! $tenant->hasFeature($feature)) {
            abort(403, 'This feature is not enabled for your workspace.');
        }

        return $next($request);
    }
}
