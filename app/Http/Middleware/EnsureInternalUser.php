<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to internal users. External customer representatives are
 * forbidden (403) from internal areas (team, customers, jobs, inventory, etc.).
 */
class EnsureInternalUser
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isExternal()) {
            abort(403);
        }

        return $next($request);
    }
}
