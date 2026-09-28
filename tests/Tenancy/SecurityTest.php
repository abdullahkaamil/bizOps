<?php

use App\Actions\Tenancy\CreateTenant;
use App\Domain\CRM\Models\Customer;
use App\Domain\Documents\DocumentService;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Jobs\Actions\CreateJobAction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\Models\Activity;

function secTenant(string $slug = 'alpha'): Tenant
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

function secOwner(Tenant $tenant, string $slug = 'alpha'): User
{
    return $tenant->run(fn () => User::where('email', "owner@{$slug}.test")->first());
}

test('security headers are present on responses', function () {
    $tenant = secTenant();

    $this->actingAs(secOwner($tenant))
        ->get('http://alpha.kaamil.test/dashboard')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeaderMissing('X-Powered-By')
        ->assertHeader('Content-Security-Policy');
});

test('login is rate limited after repeated failures', function () {
    $tenant = secTenant();
    secOwner($tenant); // ensure the user exists

    // Fortify's login limiter is 5/minute by (email|ip). The 6th attempt is throttled.
    $status = null;
    foreach (range(1, 6) as $i) {
        $status = $this->post('http://alpha.kaamil.test/login', [
            'email' => 'owner@alpha.test', 'password' => 'wrong-password',
        ])->baseResponse->getStatusCode();
    }

    expect($status)->toBe(429);
});

test('the user model hides passwords and tokens from serialization', function () {
    $tenant = secTenant();

    $array = $tenant->run(fn () => User::where('email', 'owner@alpha.test')->first()->toArray());

    expect($array)->not->toHaveKey('password')
        ->and($array)->not->toHaveKey('remember_token')
        ->and($array)->not->toHaveKey('two_factor_secret')
        ->and($array)->not->toHaveKey('two_factor_recovery_codes');
});

test('a signed document URL expires', function () {
    $tenant = secTenant();
    $owner = secOwner($tenant);

    $document = $tenant->run(function () use ($owner) {
        $customer = Customer::create(['company_name' => 'C', 'status' => 'active']);
        $job = app(CreateJobAction::class)->handle(['title' => 'J', 'customer_id' => $customer->id], $owner);

        return app(DocumentService::class)->generate(DocumentType::JobServiceReport, $job, $owner);
    });

    $signed = $tenant->run(function () use ($document) {
        URL::forceRootUrl('http://alpha.kaamil.test');

        return $document->temporaryDownloadUrl(10);
    });

    // Valid now...
    $this->actingAs($owner)->get($signed)->assertOk();

    // ...but not after it expires.
    $this->travel(11)->minutes();
    $this->actingAs($owner)->get($signed)->assertForbidden();
});

test('cross-tenant file (document) access fails', function () {
    $alpha = secTenant('alpha');
    $beta = secTenant('beta');

    $alphaDoc = $alpha->run(function () {
        $owner = User::where('email', 'owner@alpha.test')->first();
        $customer = Customer::create(['company_name' => 'A', 'status' => 'active']);
        $job = app(CreateJobAction::class)->handle(['title' => 'J', 'customer_id' => $customer->id], $owner);

        return app(DocumentService::class)->generate(DocumentType::JobServiceReport, $job, $owner);
    });

    // The document row lives only in alpha's database — beta cannot resolve it.
    $betaOwner = secOwner($beta, 'beta');
    $signed = $beta->run(function () use ($alphaDoc) {
        URL::forceRootUrl('http://beta.kaamil.test');

        return $alphaDoc->temporaryDownloadUrl(10);
    });

    $this->actingAs($betaOwner)->get($signed)->assertNotFound();
});

test('cross-tenant database access fails', function () {
    $alpha = secTenant('alpha');
    $beta = secTenant('beta');

    $alphaCustomer = $alpha->run(fn () => Customer::create(['company_name' => 'AlphaCorp', 'status' => 'active']));

    // The alpha customer does not exist in beta's database.
    expect($beta->run(fn () => Customer::where('public_id', $alphaCustomer->public_id)->exists()))->toBeFalse()
        ->and($alpha->run(fn () => Customer::where('public_id', $alphaCustomer->public_id)->exists()))->toBeTrue();
});

test('audit events include the actor and are tenant-scoped', function () {
    $tenant = secTenant();

    $tenant->run(function () {
        $owner = User::where('email', 'owner@alpha.test')->first();

        // Fire the login event -> the auth listener records an attributable entry.
        event(new Login('web', $owner, false));

        $entry = Activity::where('log_name', 'auth')->where('event', 'login')->latest('id')->first();

        expect($entry)->not->toBeNull()
            ->and($entry->causer_id)->toBe($owner->id)
            ->and($entry->causer_type)->toBe(User::class);
    });

    // The audit entry lives in the tenant database, not elsewhere.
    expect($tenant->run(fn () => Activity::where('log_name', 'auth')->count()))->toBeGreaterThan(0);
});
