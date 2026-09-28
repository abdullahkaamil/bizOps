<?php

use App\Actions\Tenancy\CreateTenant;
use App\Central\Actions\BackupTenant;
use App\Central\Actions\RestoreTenant;
use App\Domain\CRM\Models\Customer;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;

function bkTenant(): Tenant
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

test('a tenant backup can be restored (restore is tested, not assumed)', function () {
    $tenant = bkTenant();

    $tenant->run(fn () => Customer::create(['company_name' => 'Backup Me Ltd', 'status' => 'active']));

    // Back up, then destroy the data.
    $path = app(BackupTenant::class)->handle($tenant);
    $tenant->run(fn () => Customer::query()->forceDelete());
    expect($tenant->run(fn () => Customer::withTrashed()->count()))->toBe(0);

    // Restore and confirm the data is back.
    app(RestoreTenant::class)->handle($tenant, $path);

    expect($tenant->run(fn () => Customer::where('company_name', 'Backup Me Ltd')->exists()))->toBeTrue();
});

test('the backup file is encrypted at rest', function () {
    $tenant = bkTenant();
    $tenant->run(fn () => Customer::create(['company_name' => 'SensitiveName', 'status' => 'active']));

    $path = app(BackupTenant::class)->handle($tenant);
    $raw = Storage::disk('local')->get($path);

    // The plaintext company name must not appear in the stored (encrypted) blob.
    expect($raw)->not->toContain('SensitiveName')
        ->and($path)->toContain('backups/'.$tenant->id.'/');
});
