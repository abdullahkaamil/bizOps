<?php

use App\Actions\Tenancy\CreateTenant;
use App\Models\Tenant;
use App\Models\User;

const ADMIN = 'http://dashboard.kaamil.test';

test('guests cannot access the central tenant admin', function () {
    $this->get(ADMIN.'/tenants')->assertRedirect();
});

test('an authenticated central admin can view the tenants list', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get(ADMIN.'/tenants')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/tenants/Index'));
});

test('the admin root redirects to the tenants dashboard', function () {
    $this->get(ADMIN.'/')->assertRedirect(ADMIN.'/tenants');
});

test('a central admin can provision a tenant over HTTP', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->post(ADMIN.'/tenants', [
            'name' => 'Acme Inc',
            'subdomain' => 'acme',
            'admin_name' => 'Acme Admin',
            'admin_email' => 'admin@acme.test',
            'admin_password' => 'password123',
            'license_expires_at' => now()->addMonth()->toDateString(),
        ])
        ->assertRedirect();

    $tenant = Tenant::first();

    expect($tenant)->not->toBeNull()
        ->and($tenant->name)->toBe('Acme Inc')
        ->and($tenant->hasActiveLicense())->toBeTrue()
        ->and($tenant->domains()->pluck('domain')->all())->toContain('acme');

    $tenant->run(function () {
        expect(User::where('email', 'admin@acme.test')->exists())->toBeTrue();
    });
});

test('provisioning requires a future license date', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->post(ADMIN.'/tenants', [
            'name' => 'Acme', 'subdomain' => 'acme',
            'admin_name' => 'A', 'admin_email' => 'a@acme.test', 'admin_password' => 'password123',
            'license_expires_at' => now()->subDay()->toDateString(),
        ])
        ->assertSessionHasErrors('license_expires_at');

    expect(Tenant::count())->toBe(0);
});

test('subdomains must be unique and not reserved', function () {
    $admin = User::factory()->create();

    (new CreateTenant)->handle([
        'name' => 'Acme', 'subdomain' => 'acme',
        'admin_name' => 'A', 'admin_email' => 'a@acme.test', 'admin_password' => 'password123',
        'license_expires_at' => now()->addMonth(),
    ]);

    $this->actingAs($admin)
        ->post(ADMIN.'/tenants', [
            'name' => 'Another', 'subdomain' => 'acme',
            'admin_name' => 'B', 'admin_email' => 'b@x.test', 'admin_password' => 'password123',
            'license_expires_at' => now()->addMonth()->toDateString(),
        ])
        ->assertSessionHasErrors('subdomain');

    $this->actingAs($admin)
        ->post(ADMIN.'/tenants', [
            'name' => 'Reserved', 'subdomain' => 'dashboard',
            'admin_name' => 'B', 'admin_email' => 'b@x.test', 'admin_password' => 'password123',
            'license_expires_at' => now()->addMonth()->toDateString(),
        ])
        ->assertSessionHasErrors('subdomain');
});

test('a central admin can delete a tenant and drop its database', function () {
    $admin = User::factory()->create();

    $tenant = (new CreateTenant)->handle([
        'name' => 'Acme', 'subdomain' => 'acme',
        'admin_name' => 'A', 'admin_email' => 'a@acme.test', 'admin_password' => 'password123',
        'license_expires_at' => now()->addMonth(),
    ]);

    $this->actingAs($admin)
        ->delete(ADMIN."/tenants/{$tenant->id}")
        ->assertRedirect();

    expect(Tenant::count())->toBe(0);
});

test('the central tenant admin is not reachable from a tenant subdomain', function () {
    $admin = User::factory()->create();

    (new CreateTenant)->handle([
        'name' => 'Acme', 'subdomain' => 'acme',
        'admin_name' => 'A', 'admin_email' => 'a@acme.test', 'admin_password' => 'password123',
        'license_expires_at' => now()->addMonth(),
    ]);

    // /tenants only exists on the admin domain, so it 404s on a tenant subdomain.
    $this->actingAs($admin)
        ->get('http://acme.kaamil.test/tenants')
        ->assertNotFound();
});
