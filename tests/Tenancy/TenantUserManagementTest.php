<?php

use App\Actions\Tenancy\CreateTenant;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Provision a tenant and return [$tenant, $adminInstance].
 *
 * @return array{0: Tenant, 1: User}
 */
function provisionTenantWithAdmin(): array
{
    $tenant = (new CreateTenant)->handle([
        'name' => 'Acme Inc',
        'subdomain' => 'acme',
        'admin_name' => 'Acme Admin',
        'admin_email' => 'admin@acme.test',
        'admin_password' => 'password123',
        'license_expires_at' => now()->addMonth(),
    ]);

    $admin = $tenant->run(fn () => User::where('email', 'admin@acme.test')->first());

    return [$tenant, $admin];
}

test('a tenant admin can view the team page on their subdomain', function () {
    [$tenant, $admin] = provisionTenantWithAdmin();

    $this->actingAs($admin)
        ->get('http://acme.kaamil.test/users')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('tenant/users/Index'));
});

test('a tenant admin can create a team member with a role', function () {
    [$tenant, $admin] = provisionTenantWithAdmin();

    $this->actingAs($admin)
        ->post('http://acme.kaamil.test/users', [
            'name' => 'New Member',
            'email' => 'member@acme.test',
            'password' => 'password123',
            'role' => Role::Member->value,
        ])
        ->assertRedirect();

    $tenant->run(function () {
        $member = User::where('email', 'member@acme.test')->first();
        expect($member)->not->toBeNull()
            ->and($member->hasRole(Role::Member->value))->toBeTrue();
    });
});

test('a member without the create permission is forbidden from creating users', function () {
    [$tenant, $admin] = provisionTenantWithAdmin();

    $member = $tenant->run(function () {
        $user = User::create([
            'name' => 'Plain Member',
            'email' => 'plain@acme.test',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole(Role::Member->value);

        return $user;
    });

    $this->actingAs($member)
        ->post('http://acme.kaamil.test/users', [
            'name' => 'Should Fail',
            'email' => 'fail@acme.test',
            'password' => 'password123',
            'role' => Role::Member->value,
        ])
        ->assertForbidden();

    $tenant->run(function () {
        expect(User::where('email', 'fail@acme.test')->exists())->toBeFalse();
    });
});

test('the delete policy prevents a user from deleting themselves', function () {
    [$tenant, $admin] = provisionTenantWithAdmin();

    $this->actingAs($admin)
        ->delete("http://acme.kaamil.test/users/{$admin->id}")
        ->assertForbidden();

    $tenant->run(function () use ($admin) {
        expect(User::whereKey($admin->id)->exists())->toBeTrue();
    });
});

test('a tenant login persists across requests to a protected page', function () {
    provisionTenantWithAdmin();

    $this->post('http://acme.kaamil.test/login', [
        'email' => 'admin@acme.test',
        'password' => 'password123',
    ])->assertRedirect();

    $this->assertAuthenticated();

    // A follow-up request in the same session must stay authenticated and
    // resolve the user from the tenant database (regression: tenancy must
    // initialize before the session/auth middleware).
    $this->get('http://acme.kaamil.test/users')->assertOk();
});

test('tenant authentication is local to the tenant database', function () {
    [$tenant] = provisionTenantWithAdmin();

    // A user that only exists in the central database.
    User::create([
        'name' => 'Central Only',
        'email' => 'central@example.test',
        'password' => Hash::make('password123'),
        'email_verified_at' => now(),
    ]);

    // The tenant admin can authenticate on the tenant subdomain.
    $this->post('http://acme.kaamil.test/login', [
        'email' => 'admin@acme.test',
        'password' => 'password123',
    ]);
    $this->assertAuthenticated();

    app('auth')->guard()->logout();

    // The central-only user cannot authenticate on the tenant subdomain.
    $this->post('http://acme.kaamil.test/login', [
        'email' => 'central@example.test',
        'password' => 'password123',
    ])->assertSessionHasErrors('email');
    $this->assertGuest();
});
