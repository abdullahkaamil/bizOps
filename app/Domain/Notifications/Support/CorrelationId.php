<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Support;

use Illuminate\Support\Str;

/**
 * Resolves the current request's correlation id (set by AssignRequestId) so it
 * can be carried onto queued notifications and preserved on failed jobs.
 */
class CorrelationId
{
    public static function current(): string
    {
        $request = request();

        $id = $request->attributes->get('request_id')
            ?? $request->headers->get('X-Request-Id');

        return is_string($id) && $id !== '' ? $id : (string) Str::uuid();
    }
}
