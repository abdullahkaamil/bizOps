<?php

namespace Database\Seeders;

use App\Actions\Tenancy\CreateTenant;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the CENTRAL database: a SaaS super-administrator and one demo tenant.
 * Tenant databases are seeded separately by Database\Seeders\TenantDatabaseSeeder.
 *
 * Note: this seeder must NOT use WithoutModelEvents — the tenant provisioning
 * pipeline (create database, migrate, seed) is driven by tenant model events.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@kaamil.test'],
            [
                'name' => 'Platform Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        // Provision a demo tenant reachable at acme.kaamil.test (admin@acme.test / password).
        if (! Domain::where('domain', 'acme')->exists()) {
            (new CreateTenant)->handle([
                'name' => 'Acme Inc',
                'subdomain' => 'acme',
                'admin_name' => 'Acme Admin',
                'admin_email' => 'admin@acme.test',
                'admin_password' => 'password',
                'license_expires_at' => now()->addYear(),
            ]);
        }
    }
}
