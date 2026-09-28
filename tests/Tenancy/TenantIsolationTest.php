<?php

use App\Actions\Tenancy\CreateTenant;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function makeTenant(string $slug): Tenant
{
    return (new CreateTenant)->handle([
        'name' => ucfirst($slug),
        'subdomain' => $slug,
        'admin_name' => ucfirst($slug).' Admin',
        'admin_email' => "admin@{$slug}.test",
        'admin_password' => 'password123',
        'license_expires_at' => now()->addMonth(),
    ]);
}

test('1. creating a tenant creates a physically separate database', function () {
    $tenant = makeTenant('alpha');

    $dbName = $tenant->database()->getName();
    expect($dbName)->toStartWith('tenant_')
        ->and($dbName)->not->toContain('-');

    $exists = DB::connection('pgsql')->selectOne(
        'SELECT 1 AS ok FROM pg_database WHERE datname = ?',
        [$dbName],
    );
    expect($exists)->not->toBeNull();
});

test('2. tenant migrations run inside the tenant database', function () {
    $tenant = makeTenant('alpha');

    $tenant->run(function () {
        expect(Schema::hasTable('users'))->toBeTrue()
            ->and(Schema::hasTable('roles'))->toBeTrue();
    });
});

test('3. a tenant domain initializes the correct tenant', function () {
    makeTenant('alpha');
    makeTenant('beta');

    $this->get('http://alpha.kaamil.test/tenant/context')
        ->assertOk()->assertJson(['tenant_name' => 'Alpha']);

    $this->get('http://beta.kaamil.test/tenant/context')
        ->assertOk()->assertJson(['tenant_name' => 'Beta']);
});

test('4. the central domain does not initialize a tenant', function () {
    makeTenant('alpha');

    // The admin console root (central-only) is reachable -> we are in central context.
    $this->get('http://dashboard.kaamil.test/')->assertRedirect('http://dashboard.kaamil.test/admin/tenants');
    expect(tenancy()->initialized)->toBeFalse();
});

test('5. tenant routes 404 on the central domain', function () {
    makeTenant('alpha');

    $this->get('http://dashboard.kaamil.test/tenant/context')->assertNotFound();
});

test('6. central routes are unavailable on tenant domains', function () {
    makeTenant('alpha');
    $admin = User::factory()->create();

    $this->actingAs($admin)->get('http://alpha.kaamil.test/admin/tenants')->assertNotFound();
});

test('7 & 8. alpha and beta cannot read each other\'s data', function () {
    $alpha = makeTenant('alpha');
    $beta = makeTenant('beta');

    $alpha->run(fn () => expect(User::pluck('email')->all())->toBe(['admin@alpha.test']));
    $beta->run(fn () => expect(User::pluck('email')->all())->toBe(['admin@beta.test']));
});

test('tenant users never land in the central database', function () {
    makeTenant('alpha');

    // The tenant admin lives only in the tenant DB; central users table stays empty.
    expect(User::count())->toBe(0);
});

test('9. a suspended tenant is blocked from its workspace', function () {
    $alpha = makeTenant('alpha');
    $admin = $alpha->run(fn () => User::first());

    $alpha->update(['status' => TenantStatus::Suspended]);

    $this->actingAs($admin)
        ->get('http://alpha.kaamil.test/users')
        ->assertRedirect('http://alpha.kaamil.test/license-expired');
});

test('10. invalid domains do not leak tenant details', function () {
    makeTenant('alpha');

    $response = $this->get('http://ghost.kaamil.test/');

    $response->assertNotFound();
    expect($response->getContent())->not->toContain('alpha')
        ->and($response->getContent())->not->toContain('Alpha');
});
