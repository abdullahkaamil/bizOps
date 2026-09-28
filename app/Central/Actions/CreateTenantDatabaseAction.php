<?php

declare(strict_types=1);

namespace App\Central\Actions;

use App\Models\Tenant;

/**
 * Creates the tenant's PostgreSQL database. Idempotent: an existing database is
 * detected and left untouched so retries are safe.
 */
class CreateTenantDatabaseAction
{
    public function handle(Tenant $tenant): void
    {
        $manager = $tenant->database()->manager();
        $name = $tenant->database()->getName();

        if ($name !== null && $manager->databaseExists($name)) {
            return;
        }

        $manager->createDatabase($tenant);
    }
}
