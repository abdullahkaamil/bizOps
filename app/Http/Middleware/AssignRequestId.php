<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assigns a correlation id to every request and adds it (plus the active tenant)
 * to the structured logging context, then echoes it back as an X-Request-Id
 * response header.
 */
class AssignRequestId
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->headers->get('X-Request-Id') ?: (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);

        $context = ['request_id' => $requestId];

        if (tenancy()->initialized) {
            $context['tenant_id'] = tenant('id');
        }

        if ($userId = $request->user()?->getAuthIdentifier()) {
            $context['user_id'] = $userId;
        }

        Log::withContext($context);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
