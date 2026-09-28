<?php

declare(strict_types=1);

namespace App\Central\Actions;

use App\Enums\TenantStatus;
use App\Models\Tenant;

/**
 * Marks a fully provisioned tenant as active.
 */
class ActivateTenantAction
{
    public function handle(Tenant $tenant): void
    {
        $tenant->update(['status' => TenantStatus::Active]);
    }
}
