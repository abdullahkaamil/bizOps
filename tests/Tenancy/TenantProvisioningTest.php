<?php

use App\Actions\Tenancy\CreateTenant;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

function provisionAcme(): Tenant
{
    return (new CreateTenant)->handle([
        'name' => 'Acme Inc',
        'subdomain' => 'acme',
        'admin_name' => 'Acme Admin',
        'admin_email' => 'admin@acme.test',
        'admin_password' => 'password',
    ]);
}

test('a tenant is provisioned with its own database, domain, roles and admin user', function () {
    $tenant = provisionAcme();

    expect(Tenant::count())->toBe(1)
        ->and($tenant->name)->toBe('Acme Inc')
        ->and($tenant->domains()->pluck('domain')->all())->toContain('acme');

    $tenant->run(function () {
        expect(RoleModel::count())->toBe(3)
            ->and(PermissionModel::count())->toBe(6);

        $admin = User::where('email', 'admin@acme.test')->first();

        expect($admin)->not->toBeNull()
            ->and($admin->hasRole(Role::Admin->value))->toBeTrue()
            ->and($admin->can('users.delete'))->toBeTrue();
    });
});

test('tenant users are isolated from the central database', function () {
    expect(User::count())->toBe(0);

    $tenant = provisionAcme();

    // The admin user exists only inside the tenant database.
    expect(User::count())->toBe(0);

    $tenant->run(function () {
        expect(User::count())->toBe(1);
    });
});

test('two tenants have fully isolated data', function () {
    $acme = provisionAcme();

    $globex = (new CreateTenant)->handle([
        'name' => 'Globex',
        'subdomain' => 'globex',
        'admin_name' => 'Globex Admin',
        'admin_email' => 'admin@globex.test',
        'admin_password' => 'password',
    ]);

    $acme->run(function () {
        expect(User::pluck('email')->all())->toBe(['admin@acme.test']);
    });

    $globex->run(function () {
        expect(User::pluck('email')->all())->toBe(['admin@globex.test']);
    });
});
