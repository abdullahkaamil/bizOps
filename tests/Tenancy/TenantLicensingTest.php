<?php

use App\Actions\Tenancy\CreateTenant;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonInterface;

function provisionLicensedTenant(?CarbonInterface $expiresAt = null): Tenant
{
    return (new CreateTenant)->handle([
        'name' => 'Acme Inc',
        'subdomain' => 'acme',
        'admin_name' => 'Acme Admin',
        'admin_email' => 'admin@acme.test',
        'admin_password' => 'password123',
        'license_expires_at' => $expiresAt ?? now()->addMonth(),
    ]);
}

test('a tenant with an active license can reach the app', function () {
    $tenant = provisionLicensedTenant();
    $admin = $tenant->run(fn () => User::where('email', 'admin@acme.test')->first());

    $this->actingAs($admin)
        ->get('http://acme.kaamil.test/users')
        ->assertOk();
});

test('an expired tenant is redirected to the license-expired notice', function () {
    $tenant = provisionLicensedTenant(now()->subDay());
    $admin = $tenant->run(fn () => User::where('email', 'admin@acme.test')->first());

    $this->actingAs($admin)
        ->get('http://acme.kaamil.test/users')
        ->assertRedirect('http://acme.kaamil.test/license-expired');
});

test('the license-expired notice renders for an expired tenant', function () {
    provisionLicensedTenant(now()->subDay());

    $this->get('http://acme.kaamil.test/license-expired')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('tenant/LicenseExpired'));
});

test('a central admin can renew an expired tenant, restoring access', function () {
    $centralAdmin = User::factory()->create();
    $tenant = provisionLicensedTenant(now()->subDay());

    expect($tenant->fresh()->hasActiveLicense())->toBeFalse();

    $this->actingAs($centralAdmin)
        ->post("http://dashboard.kaamil.test/admin/tenants/{$tenant->id}/renew", [
            'license_expires_at' => now()->addYear()->toDateString(),
        ])
        ->assertRedirect();

    expect($tenant->fresh()->hasActiveLicense())->toBeTrue();

    // Access is restored on the tenant subdomain.
    $admin = $tenant->run(fn () => User::where('email', 'admin@acme.test')->first());

    $this->actingAs($admin)
        ->get('http://acme.kaamil.test/users')
        ->assertOk();
});

test('renewing rejects a past date', function () {
    $centralAdmin = User::factory()->create();
    $tenant = provisionLicensedTenant();

    $this->actingAs($centralAdmin)
        ->post("http://dashboard.kaamil.test/admin/tenants/{$tenant->id}/renew", [
            'license_expires_at' => now()->subDay()->toDateString(),
        ])
        ->assertSessionHasErrors('license_expires_at');
});
