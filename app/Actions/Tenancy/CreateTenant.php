<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Support\Facades\Hash;

/**
 * Provisions a new tenant: creates the tenant record + database, migrates it,
 * seeds tenant-local roles/permissions, and creates the tenant's first admin user.
 */
class CreateTenant
{
    /**
     * @param  array{name: string, subdomain: string, admin_name: string, admin_email: string, admin_password: string, license_expires_at?: CarbonInterface|string|null}  $data
     */
    public function handle(array $data): Tenant
    {
        // Creating the tenant fires the TenantCreated pipeline which
        // creates the tenant database and runs the tenant migrations.
        $tenant = Tenant::create([
            'name' => $data['name'],
            'license_expires_at' => $data['license_expires_at'] ?? null,
        ]);

        $tenant->domains()->create([
            'domain' => $data['subdomain'],
        ]);

        $tenant->run(function () use ($data): void {
            (new TenantDatabaseSeeder)->run();

            $admin = User::create([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => Hash::make($data['admin_password']),
                'email_verified_at' => now(),
            ]);

            $admin->assignRole(Role::Admin->value);
        });

        return $tenant;
    }
}
