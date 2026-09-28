<?php

use App\Actions\Tenancy\CreateTenant;
use App\Domain\Documents\NextDocumentNumberService;
use App\Domain\Settings\TenantSettings;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Support\Carbon;

function settingsTenant(string $slug): Tenant
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

test('settings fall back to documented defaults', function () {
    $tenant = settingsTenant('alpha');

    $tenant->run(function () {
        $settings = new TenantSettings;
        expect($settings->timezone())->toBe('UTC')
            ->and($settings->currency())->toBe('USD')
            ->and($settings->get('jobs', 'number_prefix'))->toBe('JOB');
    });
});

test('tenant settings are isolated between tenants', function () {
    $alpha = settingsTenant('alpha');
    $beta = settingsTenant('beta');

    $alpha->run(fn () => (new TenantSettings)->set('jobs', 'number_prefix', 'ALPHAJOB'));
    $beta->run(fn () => (new TenantSettings)->set('jobs', 'number_prefix', 'BETAJOB'));

    $alpha->run(fn () => expect((new TenantSettings)->get('jobs', 'number_prefix'))->toBe('ALPHAJOB'));
    $beta->run(fn () => expect((new TenantSettings)->get('jobs', 'number_prefix'))->toBe('BETAJOB'));
});

test('two tenants can use different document prefixes', function () {
    $alpha = settingsTenant('alpha');
    $beta = settingsTenant('beta');

    $alphaNumber = $alpha->run(fn () => (new NextDocumentNumberService)->next('job', 'ALPHA', 2026));
    $betaNumber = $beta->run(fn () => (new NextDocumentNumberService)->next('job', 'BETA', 2026));

    expect($alphaNumber)->toBe('ALPHA-2026-000001')
        ->and($betaNumber)->toBe('BETA-2026-000001');
});

test('sequential document numbers never duplicate', function () {
    $tenant = settingsTenant('alpha');

    $numbers = $tenant->run(function () {
        $service = new NextDocumentNumberService;

        return collect(range(1, 50))->map(fn () => $service->next('job', 'JOB', 2026))->all();
    });

    expect($numbers)->toHaveCount(50)
        ->and(array_unique($numbers))->toHaveCount(50)
        ->and($numbers[0])->toBe('JOB-2026-000001')
        ->and($numbers[49])->toBe('JOB-2026-000050');
});

test('timezone formatting uses the tenant timezone', function () {
    $tenant = settingsTenant('alpha');

    $tenant->run(function () {
        $settings = new TenantSettings;
        $settings->set('localization', 'timezone', 'Europe/Istanbul');

        $formatted = $settings->formatDateTime(Carbon::parse('2026-06-15 12:00:00', 'UTC'), 'Y-m-d H:i');

        // Istanbul is UTC+03:00.
        expect($formatted)->toBe('2026-06-15 15:00');
    });
});

test('sensitive settings are stored encrypted', function () {
    $tenant = settingsTenant('alpha');

    $tenant->run(function () {
        $settings = new TenantSettings;
        $settings->set('notifications', 'smtp_password', 's3cret-value', encrypted: true);

        $raw = TenantSetting::where('group', 'notifications')->where('key', 'smtp_password')->first();

        expect($raw->value)->not->toContain('s3cret-value')
            ->and($raw->is_encrypted)->toBeTrue()
            ->and($settings->get('notifications', 'smtp_password'))->toBe('s3cret-value');
    });
});

test('an internal user with permission can view and update settings', function () {
    $tenant = settingsTenant('alpha');
    $owner = $tenant->run(fn () => User::where('email', 'owner@alpha.test')->first());

    $this->actingAs($owner)->get('http://alpha.kaamil.test/settings/company')->assertOk();

    $this->actingAs($owner)
        ->put('http://alpha.kaamil.test/settings/company', [
            'company_name' => 'Alpha LLC',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'EUR',
        ])
        ->assertRedirect();

    $tenant->run(function () {
        $settings = new TenantSettings;
        expect($settings->get('general', 'company_name'))->toBe('Alpha LLC')
            ->and($settings->timezone())->toBe('Europe/Istanbul');
    });
});

test('the currency must be one of the supported currencies', function () {
    $tenant = settingsTenant('alpha');
    $owner = $tenant->run(fn () => User::where('email', 'owner@alpha.test')->first());
    $base = 'http://alpha.kaamil.test';

    // Each supported currency is accepted and persisted.
    foreach (['TRY', 'USD', 'EUR', 'GBP'] as $code) {
        $this->actingAs($owner)
            ->put("{$base}/settings/company", ['timezone' => 'UTC', 'currency' => $code])
            ->assertRedirect();

        expect($tenant->run(fn (): string => (new TenantSettings)->currency()))->toBe($code);
    }

    // An unsupported currency is rejected.
    $this->actingAs($owner)
        ->put("{$base}/settings/company", ['timezone' => 'UTC', 'currency' => 'JPY'])
        ->assertSessionHasErrors('currency');
});

test('external users cannot view or edit settings', function () {
    $tenant = settingsTenant('alpha');

    $external = $tenant->run(function () {
        $user = User::factory()->create(['user_type' => 'external', 'status' => 'active']);
        $user->assignRole(Role::CustomerRepresentative->value);

        return $user;
    });

    $this->actingAs($external)->get('http://alpha.kaamil.test/settings/company')->assertForbidden();
    $this->actingAs($external)->put('http://alpha.kaamil.test/settings/company', [
        'timezone' => 'UTC', 'currency' => 'USD',
    ])->assertForbidden();
});
