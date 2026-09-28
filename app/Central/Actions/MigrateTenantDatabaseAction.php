<?php

declare(strict_types=1);

namespace App\Central\Actions;

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs the tenant migrations inside the tenant database. Idempotent: the
 * migrations table tracks applied migrations, so retries only run what is pending.
 */
class MigrateTenantDatabaseAction
{
    public function handle(Tenant $tenant): void
    {
        $tenant->run(function (): void {
            Artisan::call('migrate', [
                '--path' => [database_path('migrations/tenant')],
                '--realpath' => true,
                '--force' => true,
            ]);
        });
    }
}
