<?php

use App\Central\Actions\ArchiveTenant;
use App\Central\Actions\PurgeTenant;
use App\Central\Actions\RequestTenantDeletion;
use App\Central\Actions\StartSupportSession;
use App\Central\Exceptions\TenantLifecycleException;
use App\Central\Models\SupportSession;
use App\Domain\Jobs\Models\Job;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;

// This suite runs in central context (no tenancy). Use the standard RefreshDatabase.

function caTenant(string $status = 'active'): Tenant
{
    return Tenant::factory()->create(['status' => $status]);
}

function caAdmin(): User
{
    return User::factory()->create();
}

test('tenant models require explicit tenant context (central users cannot query them)', function () {
    // No tenancy initialized: the tenant table does not exist on the central db.
    expect(fn () => Job::count())->toThrow(QueryException::class);
});

test('feature flags default to on and can be disabled', function () {
    $tenant = caTenant();

    expect($tenant->hasFeature('quotations'))->toBeTrue();

    $tenant->setFeature('quotations', false);

    expect($tenant->fresh()->hasFeature('quotations'))->toBeFalse()
        ->and($tenant->fresh()->hasFeature('jobs'))->toBeTrue();
});

test('a support session is audited with a reason and expiry', function () {
    $tenant = caTenant();
    $admin = caAdmin();

    $session = app(StartSupportSession::class)->handle($tenant, $admin, 'Investigating a billing discrepancy', 30);

    expect($session)->toBeInstanceOf(SupportSession::class)
        ->and($session->reason)->toBe('Investigating a billing discrepancy')
        ->and($session->admin_email)->toBe($admin->email)
        ->and($session->expires_at->isFuture())->toBeTrue()
        ->and($session->isActive())->toBeTrue()
        ->and(SupportSession::where('tenant_id', $tenant->id)->count())->toBe(1);
});

test('deletion is staged and cannot bypass the retention period', function () {
    $tenant = caTenant('suspended');
    $admin = caAdmin();

    // suspended -> archived
    app(ArchiveTenant::class)->handle($tenant);
    expect($tenant->fresh()->status)->toBe(TenantStatus::Archived);

    // Deletion without confirmation is refused.
    expect(fn () => app(RequestTenantDeletion::class)->handle($tenant->fresh(), false))
        ->toThrow(TenantLifecycleException::class);

    // Confirmed request -> deletion_pending with a future retention date.
    app(RequestTenantDeletion::class)->handle($tenant->fresh(), true, 30, $admin);
    $tenant->refresh();
    expect($tenant->status)->toBe(TenantStatus::DeletionPending)
        ->and($tenant->purge_after->isFuture())->toBeTrue()
        ->and($tenant->retentionHasElapsed())->toBeFalse();

    // Purging before retention elapses is blocked.
    expect(fn () => app(PurgeTenant::class)->handle($tenant->fresh()))
        ->toThrow(TenantLifecycleException::class);

    // Once retention elapses, purge succeeds.
    $tenant->update(['purge_after' => now()->subDay()]);
    app(PurgeTenant::class)->handle($tenant->fresh(), $admin);
    expect($tenant->fresh()->status)->toBe(TenantStatus::Deleted);
});

test('archiving requires the tenant to be suspended first', function () {
    $tenant = caTenant('active');

    expect(fn () => app(ArchiveTenant::class)->handle($tenant))
        ->toThrow(TenantLifecycleException::class);
});

test('the tenant list payload does not expose database credentials', function () {
    $tenant = caTenant();
    $admin = caAdmin();

    $response = $this->actingAs($admin)->get('http://dashboard.kaamil.test/admin/tenants')->assertOk();

    $payload = $response->viewData('page')['props']['tenants'] ?? [];
    $json = json_encode($payload);

    // No DB password / connection secrets leak into the list.
    expect($json)->not->toContain('my-secret-pw')
        ->and($json)->not->toContain('tenant_user')
        ->and($json)->not->toContain('password');
});
