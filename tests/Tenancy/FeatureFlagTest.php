<?php

use App\Actions\Tenancy\CreateTenant;
use App\Models\Tenant;
use App\Models\User;

function ffTenant(): Tenant
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

test('a disabled feature flag blocks the module route', function () {
    $tenant = ffTenant();
    $owner = $tenant->run(fn () => User::where('email', 'owner@alpha.test')->first());

    // Enabled by default -> quotations reachable.
    $this->actingAs($owner)->get('http://alpha.kaamil.test/quotations')->assertOk();

    // Operator disables the quotations feature (central column).
    $tenant->setFeature('quotations', false);
    // Force the next request to re-resolve the tenant from the DB.
    tenancy()->end();

    $this->actingAs($owner)->get('http://alpha.kaamil.test/quotations')->assertForbidden();
});

test('a suspended tenant is blocked from its workspace', function () {
    $tenant = ffTenant();
    $owner = $tenant->run(fn () => User::where('email', 'owner@alpha.test')->first());

    $this->actingAs($owner)->get('http://alpha.kaamil.test/dashboard')->assertOk();

    $tenant->update(['status' => 'suspended']);
    tenancy()->end();

    // The license/status middleware redirects a non-accessible tenant away.
    $this->actingAs($owner)->get('http://alpha.kaamil.test/dashboard')->assertRedirect();
});
