<?php

declare(strict_types=1);

namespace App\Central\Actions;

use App\Central\Exceptions\TenantLifecycleException;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantProvisioningLog;
use App\Models\User;

/**
 * Request staged deletion of an ARCHIVED tenant. Requires explicit double
 * confirmation and starts the retention clock — the tenant is not purged now, it
 * becomes deletion_pending until `purge_after`, giving an export/backup window.
 */
class RequestTenantDeletion
{
    public function handle(Tenant $tenant, bool $confirmed, int $retentionDays = 30, ?User $admin = null): Tenant
    {
        if (! $confirmed) {
            throw TenantLifecycleException::notConfirmed();
        }

        if ($tenant->status !== TenantStatus::Archived) {
            throw TenantLifecycleException::wrongStatus('archived');
        }

        $tenant->update([
            'status' => TenantStatus::DeletionPending,
            'deletion_requested_at' => now(),
            'purge_after' => now()->addDays(max(1, $retentionDays)),
        ]);

        TenantProvisioningLog::create(['tenant_id' => $tenant->id, 'step' => 'deletion_requested', 'status' => 'completed', 'message' => "Deletion scheduled; purge after {$tenant->purge_after?->toIso8601String()} (retention {$retentionDays}d).", 'finished_at' => now()]);

        return $tenant->refresh();
    }
}
