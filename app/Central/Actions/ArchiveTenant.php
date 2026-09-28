<?php

declare(strict_types=1);

namespace App\Central\Actions;

use App\Central\Exceptions\TenantLifecycleException;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantProvisioningLog;

/**
 * Move a suspended tenant to archived — the stage before deletion may be requested.
 */
class ArchiveTenant
{
    public function handle(Tenant $tenant): Tenant
    {
        if ($tenant->status !== TenantStatus::Suspended) {
            throw TenantLifecycleException::wrongStatus('suspended');
        }

        $tenant->update(['status' => TenantStatus::Archived]);

        TenantProvisioningLog::create(['tenant_id' => $tenant->id, 'step' => 'archived', 'status' => 'completed', 'message' => 'Tenant archived.', 'finished_at' => now()]);

        return $tenant->refresh();
    }
}
