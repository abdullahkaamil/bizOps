<?php

use App\Actions\Tenancy\CreateTenant;
use App\Domain\CRM\Models\Customer;
use App\Domain\Jobs\Actions\CreateJobAction;
use App\Domain\Jobs\Models\Job;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;

function c360Tenant(string $slug = 'alpha'): Tenant
{
    return (new CreateTenant)->handle([
        'name' => 'Alpha',
        'subdomain' => $slug,
        'admin_name' => ucfirst($slug).' Owner',
        'admin_email' => "owner@{$slug}.test",
        'admin_password' => 'password123',
        'license_expires_at' => now()->addMonth(),
    ]);
}

function c360Owner(Tenant $tenant): User
{
    return $tenant->run(fn () => User::where('email', 'owner@alpha.test')->first());
}

test('the initial profile request loads only summary, contacts, addresses and counts', function () {
    $tenant = c360Tenant();
    $customer = $tenant->run(function () {
        $c = Customer::create(['company_name' => 'Client Co', 'status' => 'active']);
        $tech = User::factory()->create(['user_type' => 'internal', 'status' => 'active']);
        // Create several jobs so a full history would be large.
        foreach (range(1, 15) as $i) {
            app(CreateJobAction::class)->handle(['title' => "Job {$i}", 'customer_id' => $c->id], $tech);
        }

        return $c;
    });

    $this->actingAs(c360Owner($tenant))
        ->get("http://alpha.kaamil.test/customers/{$customer->public_id}")
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('customers/Show')
            ->has('customer')
            ->has('contacts')
            ->has('addresses')
            ->has('counts')
            // Lazy history props are NOT loaded on the initial request.
            ->missing('jobs')
            ->missing('documents')
            ->missing('activities'));
});

test('counts reflect the customer\'s open records', function () {
    $tenant = c360Tenant();
    $customer = $tenant->run(function () {
        $c = Customer::create(['company_name' => 'Client Co', 'status' => 'active']);
        $tech = User::factory()->create(['user_type' => 'internal', 'status' => 'active']);
        app(CreateJobAction::class)->handle(['title' => 'A', 'customer_id' => $c->id], $tech); // pending
        app(CreateJobAction::class)->handle(['title' => 'B', 'customer_id' => $c->id], $tech); // pending

        return $c;
    });

    $this->actingAs(c360Owner($tenant))
        ->get("http://alpha.kaamil.test/customers/{$customer->public_id}")
        ->assertInertia(fn ($p) => $p->where('counts.open_jobs', 2)->where('counts.devices', 0));
});

test('a history tab returns a paginator resolved only on partial reload', function () {
    $tenant = c360Tenant();
    $customer = $tenant->run(function () {
        $c = Customer::create(['company_name' => 'Client Co', 'status' => 'active']);
        $tech = User::factory()->create(['user_type' => 'internal', 'status' => 'active']);
        foreach (range(1, 15) as $i) {
            app(CreateJobAction::class)->handle(['title' => "Job {$i}", 'customer_id' => $c->id], $tech);
        }

        return $c;
    });

    $url = "http://alpha.kaamil.test/customers/{$customer->public_id}";
    $owner = c360Owner($tenant);

    // The Inertia asset version from the full page load, needed for the partial.
    $version = $this->actingAs($owner)->get($url)->viewData('page')['version'];

    // Partial reload for the jobs tab -> a paginator with 10 per page.
    $response = $this->actingAs($owner)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Partial-Component' => 'customers/Show',
            'X-Inertia-Partial-Data' => 'jobs',
        ])
        ->get($url);

    $response->assertOk()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonCount(10, 'props.jobs.data')
        ->assertJsonPath('props.jobs.total', 15)
        // Only the requested prop is resolved on a partial reload.
        ->assertJsonMissingPath('props.documents');
});

test('counts exclude soft-deleted records', function () {
    $tenant = c360Tenant();
    [$customer, $jobId] = $tenant->run(function () {
        $c = Customer::create(['company_name' => 'Client Co', 'status' => 'active']);
        $tech = User::factory()->create(['user_type' => 'internal', 'status' => 'active']);
        $job = app(CreateJobAction::class)->handle(['title' => 'A', 'customer_id' => $c->id], $tech);

        return [$c, $job->id];
    });

    $this->actingAs(c360Owner($tenant))
        ->get("http://alpha.kaamil.test/customers/{$customer->public_id}")
        ->assertInertia(fn ($p) => $p->where('counts.open_jobs', 1));

    // Soft-delete the job; the count drops.
    $tenant->run(fn () => Job::find($jobId)->delete());

    $this->actingAs(c360Owner($tenant))
        ->get("http://alpha.kaamil.test/customers/{$customer->public_id}")
        ->assertInertia(fn ($p) => $p->where('counts.open_jobs', 0));
});

test('external users cannot access the customer 360 page', function () {
    $tenant = c360Tenant();
    $customer = $tenant->run(fn () => Customer::create(['company_name' => 'Client Co', 'status' => 'active']));
    $external = $tenant->run(function () {
        $u = User::factory()->create(['user_type' => 'external', 'status' => 'active']);
        $u->assignRole(Role::CustomerRepresentative->value);

        return $u;
    });

    // The customers routes are internal-only.
    $this->actingAs($external)
        ->get("http://alpha.kaamil.test/customers/{$customer->public_id}")
        ->assertForbidden();
});

test('the 360 profile is isolated per tenant', function () {
    $alpha = c360Tenant('alpha');
    $beta = c360Tenant('beta');

    $alphaCustomer = $alpha->run(fn () => Customer::create(['company_name' => 'Alpha Co', 'status' => 'active']));
    $betaOwner = $beta->run(fn () => User::where('email', 'owner@beta.test')->first());

    $this->actingAs($betaOwner)
        ->get("http://beta.kaamil.test/customers/{$alphaCustomer->public_id}")
        ->assertNotFound();
});
