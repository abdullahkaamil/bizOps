<?php

use App\Actions\Tenancy\CreateTenant;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantMigrationRun;

function batchTenant(string $slug = 'acme'): Tenant
{
    return (new CreateTenant)->handle([
        'name' => ucfirst($slug),
        'subdomain' => $slug,
        'admin_name' => ucfirst($slug).' Owner',
        'admin_email' => "owner@{$slug}.test",
        'admin_password' => 'password123',
        'license_expires_at' => now()->addMonth(),
    ]);
}

test('batched migration records a completed run per tenant', function () {
    $tenant = batchTenant();

    $this->artisan('tenants:migrate-batched', ['--release' => 'r1'])
        ->assertExitCode(0);

    $run = TenantMigrationRun::where('tenant_id', $tenant->id)->where('release', 'r1')->first();

    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(TenantMigrationRun::STATUS_COMPLETED)
        ->and($run->attempts)->toBe(1)
        ->and($run->finished_at)->not->toBeNull();
});

test('batched migration skips tenants without a live database', function () {
    $tenant = batchTenant();
    // A deletion-pending tenant is being torn down and must not be migrated.
    $tenant->update(['status' => TenantStatus::DeletionPending->value]);

    $this->artisan('tenants:migrate-batched', ['--release' => 'r1'])
        ->assertExitCode(0);

    expect(TenantMigrationRun::where('tenant_id', $tenant->id)->exists())->toBeFalse();
});

test('retry-failed re-runs only the tenants whose last attempt failed', function () {
    $tenant = batchTenant();

    // Simulate a prior failed attempt for release r2.
    TenantMigrationRun::create([
        'tenant_id' => $tenant->id,
        'release' => 'r2',
        'status' => TenantMigrationRun::STATUS_FAILED,
        'attempts' => 1,
        'error_class' => 'RuntimeException',
        'error_message' => 'boom',
    ]);

    $this->artisan('tenants:migrate-batched', ['--release' => 'r2', '--retry-failed' => true])
        ->assertExitCode(0);

    $run = TenantMigrationRun::where('tenant_id', $tenant->id)->where('release', 'r2')->first();

    expect($run->status)->toBe(TenantMigrationRun::STATUS_COMPLETED)
        ->and($run->attempts)->toBe(2); // incremented on the retry
});

test('retry-failed does nothing when there are no failed tenants', function () {
    batchTenant();

    $this->artisan('tenants:migrate-batched', ['--release' => 'r3', '--retry-failed' => true])
        ->assertExitCode(0);

    expect(TenantMigrationRun::where('release', 'r3')->count())->toBe(0);
});
