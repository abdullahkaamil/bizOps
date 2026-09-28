<?php

declare(strict_types=1);

namespace App\Central\Exceptions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Raised when a tenant lifecycle transition is invalid — e.g. purging before the
 * retention period has elapsed, or a deletion request without confirmation.
 */
class TenantLifecycleException extends RuntimeException
{
    public static function retentionNotElapsed(): self
    {
        return new self('The retention period has not elapsed; this tenant cannot be purged yet.');
    }

    public static function notConfirmed(): self
    {
        return new self('Tenant deletion requires explicit double confirmation.');
    }

    public static function wrongStatus(string $expected): self
    {
        return new self("This action requires the tenant to be {$expected}.");
    }

    public function render(Request $request): RedirectResponse
    {
        return back()->withErrors(['tenant' => $this->getMessage()]);
    }
}
