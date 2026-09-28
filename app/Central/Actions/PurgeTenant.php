<?php

declare(strict_types=1);

namespace App\Central\Actions;

use App\Central\Exceptions\TenantLifecycleException;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantProvisioningLog;
use App\Models\User;
use Throwable;

/**
 * Permanently purge a deletion-pending tenant AFTER its retention period has
 * elapsed. The retention guard cannot be bypassed. Drops the tenant database and
 * tombstones the row as `deleted` for audit.
 */
class PurgeTenant
{
    public function handle(Tenant $tenant, ?User $admin = null): Tenant
    {
        if ($tenant->status !== TenantStatus::DeletionPending) {
            throw TenantLifecycleException::wrongStatus('deletion_pending');
        }

        if (! $tenant->retentionHasElapsed()) {
            throw TenantLifecycleException::retentionNotElapsed();
        }

        // Drop the tenant database if it still exists.
        try {
            if ($tenant->database()->manager()->databaseExists($db = $tenant->database()->getName())) {
                $tenant->database()->manager()->deleteDatabase($tenant);
            }
        } catch (Throwable) {
            // Best-effort DB drop; the row is still tombstoned below.
        }

        $tenant->update(['status' => TenantStatus::Deleted]);

        TenantProvisioningLog::create(['tenant_id' => $tenant->id, 'step' => 'purged', 'status' => 'completed', 'message' => 'Tenant permanently purged.', 'finished_at' => now()]);

        return $tenant->refresh();
    }
}
