<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs a callback once inside each *active* tenant's database context, for
 * central scheduler commands that fan out across tenants.
 *
 * Guarantees the queue/scheduler plan depends on:
 *  - Only active tenants are visited (suspended/archived/provisioning/deleted are
 *    skipped — a suspended tenant must not receive reminders).
 *  - Each tenant is initialized safely via `$tenant->run()`.
 *  - A failure in one tenant is logged and does NOT stop the others.
 *
 * The callback runs in tenant context and receives the current Tenant.
 */
class ActiveTenants
{
    /**
     * @param  Closure(Tenant):void  $callback
     * @return array{processed: int, failed: int}
     */
    public function each(Closure $callback): array
    {
        $processed = 0;
        $failed = 0;

        Tenant::query()
            ->where('status', TenantStatus::Active->value)
            ->cursor()
            ->each(function (Tenant $tenant) use ($callback, &$processed, &$failed): void {
                try {
                    $tenant->run(function () use ($tenant, $callback): void {
                        $callback($tenant);
                    });
                    $processed++;
                } catch (Throwable $e) {
                    // Isolate the failure: log it with the tenant id and carry on
                    // so one broken tenant cannot stall the whole run.
                    $failed++;
                    Log::error('Scheduled tenant run failed', [
                        'tenant_id' => $tenant->id,
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                }
            });

        return ['processed' => $processed, 'failed' => $failed];
    }
}
