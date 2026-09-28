<?php

use App\Actions\Tenancy\CreateTenant;
use App\Domain\CRM\Models\Customer;
use App\Domain\CRM\Models\CustomerAddress;
use App\Domain\CRM\Models\CustomerContact;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;

function crmTenant(string $slug = 'alpha'): Tenant
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

function crmOwner(Tenant $tenant): User
{
    return $tenant->run(fn () => User::where('email', 'owner@'.$tenant->slug.'.test')->first());
}

test('an owner can create, view, update and soft-delete a customer', function () {
    $tenant = crmTenant();
    $owner = crmOwner($tenant);

    // Create
    $this->actingAs($owner)
        ->post('http://alpha.kaamil.test/customers', [
            'company_name' => 'Acme Widgets', 'status' => 'active', 'email' => 'hi@acme.test',
        ])
        ->assertRedirect();

    $customer = $tenant->run(fn () => Customer::first());
    expect($customer->company_name)->toBe('Acme Widgets')
        ->and($customer->created_by)->toBe($owner->id);

    // View
    $this->actingAs($owner)
        ->get("http://alpha.kaamil.test/customers/{$customer->public_id}")
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('customers/Show'));

    // Update
    $this->actingAs($owner)
        ->put("http://alpha.kaamil.test/customers/{$customer->public_id}", [
            'company_name' => 'Acme Renamed', 'status' => 'inactive',
        ])
        ->assertRedirect();
    expect($tenant->run(fn () => Customer::first()->company_name))->toBe('Acme Renamed');

    // Soft delete
    $this->actingAs($owner)
        ->delete("http://alpha.kaamil.test/customers/{$customer->public_id}")
        ->assertRedirect();

    $tenant->run(function () {
        expect(Customer::count())->toBe(0)
            ->and(Customer::withTrashed()->count())->toBe(1);
    });
});

test('customers are isolated between tenants', function () {
    $alpha = crmTenant('alpha');
    $beta = crmTenant('beta');

    $alpha->run(fn () => Customer::create(['company_name' => 'Alpha Co', 'status' => 'active']));

    $alpha->run(fn () => expect(Customer::count())->toBe(1));
    $beta->run(fn () => expect(Customer::count())->toBe(0));
});

test('external users cannot access the CRM', function () {
    $tenant = crmTenant();
    $external = $tenant->run(function () {
        $user = User::factory()->create(['user_type' => 'external', 'status' => 'active']);
        $user->assignRole(Role::CustomerRepresentative->value);

        return $user;
    });

    $this->actingAs($external)->get('http://alpha.kaamil.test/customers')->assertForbidden();
    $this->actingAs($external)
        ->post('http://alpha.kaamil.test/customers', ['company_name' => 'X', 'status' => 'active'])
        ->assertForbidden();
});

test('search is scoped to the tenant and matches company, email and contact name', function () {
    $tenant = crmTenant();
    $owner = crmOwner($tenant);

    $tenant->run(function () {
        $a = Customer::create(['company_name' => 'Northwind Traders', 'status' => 'active']);
        $a->contacts()->create(['first_name' => 'Nancy', 'last_name' => 'Davolio']);
        Customer::create(['company_name' => 'Southwind', 'status' => 'active', 'email' => 'sales@south.test']);
    });

    $this->actingAs($owner)
        ->get('http://alpha.kaamil.test/customers?search=Northwind')
        ->assertInertia(fn ($p) => $p->where('customers.total', 1));

    $this->actingAs($owner)
        ->get('http://alpha.kaamil.test/customers?search=Nancy')
        ->assertInertia(fn ($p) => $p->where('customers.total', 1));

    $this->actingAs($owner)
        ->get('http://alpha.kaamil.test/customers?search=south.test')
        ->assertInertia(fn ($p) => $p->where('customers.total', 1));
});

test('the first contact becomes primary and switching primary demotes the old one', function () {
    $tenant = crmTenant();
    $owner = crmOwner($tenant);
    $customer = $tenant->run(fn () => Customer::create(['company_name' => 'X', 'status' => 'active']));

    $this->actingAs($owner)->post("http://alpha.kaamil.test/customers/{$customer->public_id}/contacts", [
        'first_name' => 'First', 'last_name' => 'One',
    ]);
    $this->actingAs($owner)->post("http://alpha.kaamil.test/customers/{$customer->public_id}/contacts", [
        'first_name' => 'Second', 'last_name' => 'Two', 'is_primary' => true,
    ]);

    $tenant->run(function () {
        $primaries = CustomerContact::where('is_primary', true)->get();
        expect($primaries)->toHaveCount(1)
            ->and($primaries->first()->first_name)->toBe('Second');
    });
});

test('the first address becomes primary and switching primary demotes the old one', function () {
    $tenant = crmTenant();
    $owner = crmOwner($tenant);
    $customer = $tenant->run(fn () => Customer::create(['company_name' => 'X', 'status' => 'active']));

    $base = "http://alpha.kaamil.test/customers/{$customer->public_id}/addresses";
    $this->actingAs($owner)->post($base, [
        'type' => 'billing', 'address_line_1' => 'A1', 'city' => 'Town', 'country_code' => 'US',
    ]);
    $this->actingAs($owner)->post($base, [
        'type' => 'service', 'address_line_1' => 'B2', 'city' => 'City', 'country_code' => 'US', 'is_primary' => true,
    ]);

    $tenant->run(function () {
        $primaries = CustomerAddress::where('is_primary', true)->get();
        expect($primaries)->toHaveCount(1)
            ->and($primaries->first()->address_line_1)->toBe('B2');
    });
});

test('the profile does not load history tabs on the initial request', function () {
    $tenant = crmTenant();
    $owner = crmOwner($tenant);
    $customer = $tenant->run(fn () => Customer::create(['company_name' => 'X', 'status' => 'active']));

    $this->actingAs($owner)
        ->get("http://alpha.kaamil.test/customers/{$customer->public_id}")
        ->assertInertia(fn ($p) => $p
            ->has('customer')
            ->has('contacts')
            ->has('addresses')
            ->missing('activities')
            ->missing('jobs')
            ->missing('quotations'),
        );
});
