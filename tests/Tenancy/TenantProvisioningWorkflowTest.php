<?php

use App\Central\Actions\MigrateTenantDatabaseAction;
use App\Central\Actions\TenantProvisioner;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantProvisioningLog;
use App\Models\User;

function provisionWorkflowTenant(string $slug = 'acme'): Tenant
{
    return app(TenantProvisioner::class)->provision([
        'name' => ucfirst($slug),
        'subdomain' => $slug,
        'admin_name' => ucfirst($slug).' Admin',
        'admin_email' => "owner@{$slug}.test",
        'admin_password' => 'password123',
        'license_expires_at' => now()->addMonth(),
    ]);
}

test('successful provisioning reaches active with a complete log', function () {
    $tenant = provisionWorkflowTenant();

    expect($tenant->status)->toBe(TenantStatus::Active);

    $completed = TenantProvisioningLog::where('tenant_id', $tenant->getTenantKey())
        ->where('status', 'completed')->pluck('step')->all();

    expect($completed)
        ->toContain('domain')->toContain('database')->toContain('migrate')
        ->toContain('seed')->toContain('owner')->toContain('activate');
});

test('a failed migration leaves the tenant in the failed state', function () {
    $this->mock(MigrateTenantDatabaseAction::class)
        ->shouldReceive('handle')->andThrow(new RuntimeException('migrate failed'));

    try {
        provisionWorkflowTenant();
    } catch (Throwable) {
        // expected
    }

    $tenant = Tenant::first();

    expect($tenant->status)->toBe(TenantStatus::Failed)
        ->and(TenantProvisioningLog::where('tenant_id', $tenant->getTenantKey())
            ->where('step', 'migrate')->where('status', 'failed')->exists())->toBeTrue();
});

test('retrying a failed tenant resumes safely to active', function () {
    $this->mock(MigrateTenantDatabaseAction::class)
        ->shouldReceive('handle')->andThrow(new RuntimeException('migrate failed'));

    try {
        provisionWorkflowTenant();
    } catch (Throwable) {
        // expected
    }

    $tenant = Tenant::first();
    expect($tenant->status)->toBe(TenantStatus::Failed);

    // Restore the real migrate action, then retry.
    $this->app->forgetInstance(MigrateTenantDatabaseAction::class);
    $this->app->forgetInstance(TenantProvisioner::class);

    app(TenantProvisioner::class)->retry($tenant->fresh(), [
        'subdomain' => 'acme',
        'admin_name' => 'Acme Admin',
        'admin_email' => 'owner@acme.test',
        'admin_password' => 'password123',
    ]);

    expect($tenant->fresh()->status)->toBe(TenantStatus::Active);
    $tenant->run(fn () => expect(User::where('email', 'owner@acme.test')->exists())->toBeTrue());
});

test('provisioning is idempotent for the same slug (no duplicates)', function () {
    provisionWorkflowTenant();
    provisionWorkflowTenant();

    expect(Tenant::where('slug', 'acme')->count())->toBe(1);

    $tenant = Tenant::first();
    expect($tenant->domains()->count())->toBe(1);
    $tenant->run(fn () => expect(User::where('email', 'owner@acme.test')->count())->toBe(1));
});

test('duplicate subdomain/slug is rejected at the create endpoint', function () {
    $admin = User::factory()->create();
    provisionWorkflowTenant();

    $this->actingAs($admin)
        ->post('http://dashboard.kaamil.test/admin/tenants', [
            'name' => 'Another', 'subdomain' => 'acme',
            'admin_name' => 'B', 'admin_email' => 'b@x.test', 'admin_password' => 'password123',
            'license_expires_at' => now()->addMonth()->toDateString(),
        ])
        ->assertSessionHasErrors('subdomain');
});

test('a central admin can view the provisioning timeline', function () {
    $admin = User::factory()->create();
    $tenant = provisionWorkflowTenant();

    $this->actingAs($admin)
        ->get("http://dashboard.kaamil.test/admin/tenants/{$tenant->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/tenants/Show'));
});

test('a central admin can suspend and reactivate a tenant', function () {
    $admin = User::factory()->create();
    $tenant = provisionWorkflowTenant();

    $this->actingAs($admin)->post("http://dashboard.kaamil.test/admin/tenants/{$tenant->id}/suspend")->assertRedirect();
    expect($tenant->fresh()->status)->toBe(TenantStatus::Suspended);

    $this->actingAs($admin)->post("http://dashboard.kaamil.test/admin/tenants/{$tenant->id}/reactivate")->assertRedirect();
    expect($tenant->fresh()->status)->toBe(TenantStatus::Active);
});

test('a suspended tenant cannot enter the tenant app', function () {
    $tenant = provisionWorkflowTenant();
    $owner = $tenant->run(fn () => User::first());

    $tenant->update(['status' => TenantStatus::Suspended]);

    $this->actingAs($owner)
        ->get('http://acme.kaamil.test/users')
        ->assertRedirect('http://acme.kaamil.test/license-expired');
});

test('a reactivated tenant can enter the tenant app', function () {
    $tenant = provisionWorkflowTenant();
    $owner = $tenant->run(fn () => User::first());

    // Suspend then reactivate before the first tenant-domain request.
    $tenant->update(['status' => TenantStatus::Suspended]);
    $tenant->update(['status' => TenantStatus::Active]);

    $this->actingAs($owner)->get('http://acme.kaamil.test/users')->assertOk();
});
