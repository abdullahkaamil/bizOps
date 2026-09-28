<?php

declare(strict_types=1);

namespace App\Central\Actions;

use App\Enums\TenantStatus;
use App\Models\Tenant;

/**
 * Leaves a tenant in the failed (non-active) state after a provisioning error.
 */
class MarkTenantProvisioningFailedAction
{
    public function handle(Tenant $tenant): void
    {
        $tenant->update(['status' => TenantStatus::Failed]);
    }
}
