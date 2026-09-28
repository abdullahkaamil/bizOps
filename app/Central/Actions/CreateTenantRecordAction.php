<?php

declare(strict_types=1);

namespace App\Central\Actions;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Carbon\CarbonInterface;

/**
 * Creates (or returns, on retry) the central tenant record in provisioning state.
 * Idempotent by slug.
 */
class CreateTenantRecordAction
{
    /**
     * @param  array{name: string, subdomain: string, license_expires_at?: CarbonInterface|string|null}  $data
     */
    public function handle(array $data): Tenant
    {
        return Tenant::firstOrCreate(
            ['slug' => $data['subdomain']],
            [
                'name' => $data['name'],
                'status' => TenantStatus::Provisioning,
                'license_expires_at' => $data['license_expires_at'] ?? null,
            ],
        );
    }
}
