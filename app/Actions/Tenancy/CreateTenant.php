<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Central\Actions\TenantProvisioner;
use App\Models\Tenant;
use Carbon\CarbonInterface;

/**
 * Thin convenience wrapper over the Phase 4 TenantProvisioner. Kept for callers
 * (seeder, tests) that provision a tenant in one call.
 */
class CreateTenant
{
    /**
     * @param  array{name: string, subdomain: string, admin_name: string, admin_email: string, admin_password: string, license_expires_at?: CarbonInterface|string|null}  $data
     */
    public function handle(array $data): Tenant
    {
        return app(TenantProvisioner::class)->provision($data);
    }
}
