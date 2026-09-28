<?php

declare(strict_types=1);

namespace App\Central\Actions;

use App\Models\Tenant;

/**
 * Reserves the tenant's subdomain. Idempotent: a retry does not duplicate the
 * domain (the unique constraint also protects against cross-tenant collisions).
 */
class CreateTenantDomainAction
{
    public function handle(Tenant $tenant, string $domain): void
    {
        if ($tenant->domains()->where('domain', $domain)->exists()) {
            return;
        }

        $tenant->domains()->create(['domain' => $domain]);
    }
}
