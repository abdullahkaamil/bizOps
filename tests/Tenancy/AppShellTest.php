<?php

use App\Actions\Tenancy\CreateTenant;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;

function shellTenant(): Tenant
{
    return (new CreateTenant)->handle([
        'name' => 'Alpha',
        'subdomain' => 'alpha',
        'admin_name' => 'Alpha Owner',
        'admin_email' => 'owner@alpha.test',
        'admin_password' => 'password123',
        'license_expires_at' => now()->addMonth(),
    ]);
}

test('an internal user gets internal user type and module permissions (full sidebar)', function () {
    $tenant = shellTenant();
    $owner = $tenant->run(fn () => User::where('email', 'owner@alpha.test')->first());

    $this->actingAs($owner)
        ->get('http://alpha.kaamil.test/')
        ->assertInertia(fn ($page) => $page
            ->where('auth.userType', 'internal')
            ->where('auth.isCentralAdmin', false)
            ->where('auth.permissions', fn ($p) => $p->contains('users.view') && $p->contains('settings.view')),
        );
});

test('an external user gets external type with internal modules hidden', function () {
    $tenant = shellTenant();
    $external = $tenant->run(function () {
        $user = User::factory()->create(['user_type' => 'external', 'status' => 'active']);
        $user->assignRole(Role::CustomerRepresentative->value);

        return $user;
    });

    $this->actingAs($external)
        ->get('http://alpha.kaamil.test/')
        ->assertInertia(fn ($page) => $page
            ->where('auth.userType', 'external')
            ->where('auth.permissions', fn ($p) => ! $p->contains('users.view') && ! $p->contains('settings.view')),
        );
});

test('a central admin gets the central admin shell', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get('http://dashboard.kaamil.test/admin/tenants')
        ->assertInertia(fn ($page) => $page
            ->component('admin/tenants/Index')
            ->where('auth.isCentralAdmin', true),
        );
});

test('forms surface server-side validation errors', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->post('http://dashboard.kaamil.test/admin/tenants', [
            'name' => '',
            'subdomain' => 'A B C',
            'admin_email' => 'not-an-email',
        ])
        ->assertInvalid(['name', 'subdomain', 'admin_email']);
});
