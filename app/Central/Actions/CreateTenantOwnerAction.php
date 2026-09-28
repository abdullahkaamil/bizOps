<?php

declare(strict_types=1);

namespace App\Central\Actions;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the tenant's first owner/admin user inside the tenant database.
 * Idempotent: an existing user (by email) is reused, not duplicated.
 */
class CreateTenantOwnerAction
{
    /**
     * @param  array{name: string, email: string, password: string}  $owner
     */
    public function handle(Tenant $tenant, array $owner): void
    {
        $tenant->run(function () use ($owner): void {
            $user = User::firstOrCreate(
                ['email' => $owner['email']],
                [
                    'name' => $owner['name'],
                    'password' => Hash::make($owner['password']),
                    'user_type' => Role::Owner->userType()->value,
                    'email_verified_at' => now(),
                ],
            );

            if (! $user->hasRole(Role::Owner->value)) {
                $user->assignRole(Role::Owner->value);
            }
        });
    }
}
