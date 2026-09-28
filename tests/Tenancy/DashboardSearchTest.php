<?php

use App\Actions\Tenancy\CreateTenant;
use App\Domain\CRM\Models\Customer;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Jobs\Actions\CreateJobAction;
use App\Domain\Jobs\Models\Job;
use App\Domain\Quotations\Actions\CreateQuotationAction;
use App\Domain\Workshop\Actions\CreateWorkshopTicketAction;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;

function dsTenant(string $slug = 'alpha'): Tenant
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

function dsUser(string $role, string $type = 'internal'): User
{
    $u = User::factory()->create(['user_type' => $type, 'status' => 'active']);
    $u->assignRole($role);

    return $u;
}

// ---------------------------------------------------------------------------
// Dashboard
// ---------------------------------------------------------------------------

test('the dashboard is role-aware', function () {
    $tenant = dsTenant();
    [$manager, $tech] = $tenant->run(fn () => [dsUser(Role::Manager->value), dsUser(Role::Technician->value)]);

    // Manager sees management widgets (low stock, draft quotations, activity).
    $this->actingAs($manager)
        ->get('http://alpha.kaamil.test/dashboard')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('Dashboard')
            ->where('dashboard.scope', 'internal')
            ->has('dashboard.widgets.low_stock')
            ->has('dashboard.widgets.draft_quotations')
            ->has('dashboard.widgets.recent_activity'));

    // Technician does NOT see those; sees workshop/jobs widgets instead.
    $this->actingAs($tech)
        ->get('http://alpha.kaamil.test/dashboard')
        ->assertInertia(fn ($p) => $p
            ->where('dashboard.scope', 'internal')
            ->missing('dashboard.widgets.low_stock')
            ->missing('dashboard.widgets.draft_quotations')
            ->missing('dashboard.widgets.recent_activity')
            ->has('dashboard.widgets.active_workshop'));
});

test('the external dashboard contains no internal data', function () {
    $tenant = dsTenant();
    $external = $tenant->run(fn () => dsUser(Role::CustomerRepresentative->value, 'external'));

    $this->actingAs($external)
        ->get('http://alpha.kaamil.test/dashboard')
        ->assertInertia(fn ($p) => $p
            ->where('dashboard.scope', 'external')
            ->has('dashboard.widgets.assigned_boards')
            ->has('dashboard.widgets.tasks_awaiting_approval')
            ->missing('dashboard.widgets.my_jobs')
            ->missing('dashboard.widgets.low_stock')
            ->missing('dashboard.widgets.active_workshop'));
});

// ---------------------------------------------------------------------------
// Search
// ---------------------------------------------------------------------------

test('a serial number finds its device and a job number is searchable', function () {
    $tenant = dsTenant();
    $manager = $tenant->run(function () {
        $m = dsUser(Role::Manager->value);
        $customer = Customer::create(['company_name' => 'Globex', 'status' => 'active']);
        app(CreateWorkshopTicketAction::class)->handle([
            'customer_id' => $customer->id, 'serial_number' => 'ZX-99887', 'brand' => 'Acme', 'model' => 'Router', 'issue_description' => 'x',
        ], $m);
        app(CreateJobAction::class)->handle(['title' => 'Install fibre', 'customer_id' => $customer->id], $m);

        return $m;
    });

    // Serial-number search finds the device.
    $this->actingAs($manager)
        ->getJson('http://alpha.kaamil.test/search/results?q=ZX-99887')
        ->assertOk()
        ->assertJsonFragment(['type' => 'devices'])
        ->assertJsonPath('groups.0.results.0.subtitle', 'SN ZX-99887');

    // Job number (JOB-2026-000001) is searchable.
    $jobNumber = $tenant->run(fn () => Job::first()->number);
    $this->actingAs($manager)
        ->getJson('http://alpha.kaamil.test/search/results?q='.$jobNumber)
        ->assertJsonFragment(['type' => 'jobs']);
});

test('search respects policies — a user without a permission gets no such results', function () {
    $tenant = dsTenant();
    $tech = $tenant->run(function () {
        Customer::create(['company_name' => 'SearchableCo', 'status' => 'active']);
        InventoryItem::create(['sku' => 'FINDME-1', 'name' => 'Widget', 'unit' => 'unit', 'status' => 'active']);

        // A quotation exists but the technician cannot view quotations.
        $sales = dsUser(Role::Sales->value);
        $c = Customer::first();
        app(CreateQuotationAction::class)->handle([
            'customer_id' => $c->id, 'lines' => [['customer_alias' => 'SearchableCo item', 'quantity' => 1, 'unit_price' => 5]],
        ], $sales);

        return dsUser(Role::Technician->value);
    });

    // Technician has inventory.view + customers.view, so those groups appear...
    $response = $this->actingAs($tech)->getJson('http://alpha.kaamil.test/search/results?q=Searchable')->assertOk();
    $types = collect($response->json('groups'))->pluck('type');
    expect($types)->toContain('customers')
        ->and($types)->not->toContain('quotations'); // no quotations.view -> not searched

    // Inventory is searchable for the technician (has inventory.view).
    $this->actingAs($tech)
        ->getJson('http://alpha.kaamil.test/search/results?q=FINDME')
        ->assertJsonFragment(['type' => 'inventory']);
});

test('search is tenant-scoped', function () {
    $alpha = dsTenant('alpha');
    $beta = dsTenant('beta');

    $alpha->run(fn () => Customer::create(['company_name' => 'AlphaOnlyCorp', 'status' => 'active']));
    $betaManager = $beta->run(fn () => dsUser(Role::Manager->value));

    // The beta manager cannot find the alpha-only customer.
    $this->actingAs($betaManager)
        ->getJson('http://beta.kaamil.test/search/results?q=AlphaOnlyCorp')
        ->assertOk()
        ->assertJsonPath('groups', []);
});

test('an external representative only searches their own boards', function () {
    $tenant = dsTenant();
    $external = $tenant->run(function () {
        // Data the external rep must not find.
        Customer::create(['company_name' => 'SecretCorp', 'status' => 'active']);
        InventoryItem::create(['sku' => 'SECRET-1', 'name' => 'SecretPart', 'unit' => 'unit', 'status' => 'active']);

        return dsUser(Role::CustomerRepresentative->value, 'external');
    });

    // No customers/inventory groups for an external user.
    $this->actingAs($external)
        ->getJson('http://alpha.kaamil.test/search/results?q=Secret')
        ->assertOk()
        ->assertJsonPath('groups', []);
});
