<?php

declare(strict_types=1);

namespace App\Central\Actions;

use App\Models\Tenant;
use Database\Seeders\TenantDatabaseSeeder;

/**
 * Seeds the tenant's default roles and permissions. Idempotent: the seeder uses
 * findOrCreate, so retries do not duplicate records.
 */
class SeedTenantDefaultsAction
{
    public function handle(Tenant $tenant): void
    {
        $tenant->run(fn () => (new TenantDatabaseSeeder)->run());
    }
}
