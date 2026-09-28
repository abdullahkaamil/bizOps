<?php

use App\Actions\Tenancy\CreateTenant;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role as SpatieRole;

function rbacTenant(): Tenant
{
    return (new CreateTenant)->handle([
        'name' => 'Acme',
        'subdomain' => 'acme',
        'admin_name' => 'Acme Owner',
        'admin_email' => 'owner@acme.test',
        'admin_password' => 'password123',
        'license_expires_at' => now()->addMonth(),
    ]);
}

test('the owner role has every permission', function () {
    expect(collect(Role::Owner->permissionValues())->sort()->values()->all())
        ->toBe(collect(Permission::values())->sort()->values()->all());
});

test('each seeded role receives exactly its expected permissions', function () {
    $tenant = rbacTenant();

    $tenant->run(function () {
        foreach (Role::cases() as $role) {
            $spatie = SpatieRole::where('name', $role->value)->first();

            expect($spatie)->not->toBeNull();

            $granted = collect($spatie->permissions->pluck('name'))->sort()->values()->all();
            $expected = collect($role->permissionValues())->sort()->values()->all();

            expect($granted)->toBe($expected);
        }
    });
});

test('an external customer representative cannot access internal areas', function () {
    $tenant = rbacTenant();

    $external = $tenant->run(function () {
        $user = User::factory()->create(['user_type' => 'external', 'status' => 'active']);
        $user->assignRole(Role::CustomerRepresentative->value);

        return $user;
    });

    expect($external->isExternal())->toBeTrue();

    $this->actingAs($external)->get('http://acme.kaamil.test/users')->assertForbidden();
});

test('an external user cannot bypass restrictions via a JSON request', function () {
    $tenant = rbacTenant();

    $external = $tenant->run(function () {
        $user = User::factory()->create(['user_type' => 'external', 'status' => 'active']);
        $user->assignRole(Role::CustomerRepresentative->value);

        return $user;
    });

    $this->actingAs($external)->getJson('http://acme.kaamil.test/users')->assertForbidden();
});

test('an internal user without a permission receives 403', function () {
    $tenant = rbacTenant();

    $manager = $tenant->run(function () {
        $user = User::factory()->create(['user_type' => 'internal', 'status' => 'active']);
        $user->assignRole(Role::Manager->value);

        return $user;
    });

    // Manager may view the team...
    $this->actingAs($manager)->get('http://acme.kaamil.test/users')->assertOk();

    // ...but may not create users (lacks users.create).
    $this->actingAs($manager)
        ->post('http://acme.kaamil.test/users', [
            'name' => 'X', 'email' => 'x@acme.test', 'password' => 'password123', 'role' => Role::Sales->value,
        ])
        ->assertForbidden();
});

test('the owner can perform any tenant user action', function () {
    $tenant = rbacTenant();

    $tenant->run(function () {
        $owner = User::where('email', 'owner@acme.test')->first();

        expect($owner->can(Permission::CreateUsers->value))->toBeTrue()
            ->and($owner->can(Permission::SuspendUsers->value))->toBeTrue()
            ->and($owner->can(Permission::ManageRoles->value))->toBeTrue();
    });

    $owner = $tenant->run(fn () => User::where('email', 'owner@acme.test')->first());

    $this->actingAs($owner)
        ->post('http://acme.kaamil.test/users', [
            'name' => 'New', 'email' => 'new@acme.test', 'password' => 'password123', 'role' => Role::Sales->value,
        ])
        ->assertRedirect();
});

test('role permissions conform to the suggested permission matrix (hard cells)', function () {
    // Only the matrix's non-"Optional" cells are asserted; "Optional" cells are
    // client discretion. Owner/Administrator hold every permission by definition.
    $has = fn (Role $role, Permission $p): bool => in_array($p, $role->permissions(), true);

    // Customers view: Sales Yes, External No.
    expect($has(Role::Sales, Permission::ViewCustomers))->toBeTrue()
        ->and($has(Role::CustomerRepresentative, Permission::ViewCustomers))->toBeFalse();

    // Customers manage: Manager/Sales Yes; Developer/Technician/External No.
    expect($has(Role::Manager, Permission::UpdateCustomers))->toBeTrue()
        ->and($has(Role::Sales, Permission::UpdateCustomers))->toBeTrue()
        ->and($has(Role::Developer, Permission::UpdateCustomers))->toBeFalse()
        ->and($has(Role::Technician, Permission::UpdateCustomers))->toBeFalse();

    // Internal boards (reach = ViewTasks): Manager/Developer/Technician Yes.
    expect($has(Role::Manager, Permission::ViewTasks))->toBeTrue()
        ->and($has(Role::Developer, Permission::ViewTasks))->toBeTrue()
        ->and($has(Role::Technician, Permission::ViewTasks))->toBeTrue();

    // Submit for review: Developer Yes; Sales/External No.
    expect($has(Role::Developer, Permission::SubmitTaskReview))->toBeTrue()
        ->and($has(Role::Sales, Permission::SubmitTaskReview))->toBeFalse()
        ->and($has(Role::CustomerRepresentative, Permission::SubmitTaskReview))->toBeFalse();

    // Approve review: External Yes; Developer/Technician No (permission-level).
    expect($has(Role::CustomerRepresentative, Permission::ApproveTasks))->toBeTrue()
        ->and($has(Role::Developer, Permission::ApproveTasks))->toBeFalse()
        ->and($has(Role::Technician, Permission::ApproveTasks))->toBeFalse();

    // Jobs start/complete: Technician Yes; Sales No.
    expect($has(Role::Technician, Permission::CompleteJobs))->toBeTrue()
        ->and($has(Role::Sales, Permission::CompleteJobs))->toBeFalse();

    // Jobs create: Developer No.
    expect($has(Role::Developer, Permission::CreateJobs))->toBeFalse();

    // Workshop complete: Manager/Technician Yes; Developer/Sales No.
    expect($has(Role::Manager, Permission::CompleteWorkshop))->toBeTrue()
        ->and($has(Role::Technician, Permission::CompleteWorkshop))->toBeTrue()
        ->and($has(Role::Developer, Permission::CompleteWorkshop))->toBeFalse()
        ->and($has(Role::Sales, Permission::CompleteWorkshop))->toBeFalse();

    // Inventory view: Technician/Sales Yes; External No.
    expect($has(Role::Technician, Permission::ViewInventory))->toBeTrue()
        ->and($has(Role::Sales, Permission::ViewInventory))->toBeTrue()
        ->and($has(Role::CustomerRepresentative, Permission::ViewInventory))->toBeFalse();

    // Inventory cost: Developer No.
    expect($has(Role::Developer, Permission::ViewInventoryCost))->toBeFalse();

    // Quotations create: Sales Yes; Developer/Technician/External No.
    expect($has(Role::Sales, Permission::CreateQuotations))->toBeTrue()
        ->and($has(Role::Developer, Permission::CreateQuotations))->toBeFalse()
        ->and($has(Role::Technician, Permission::CreateQuotations))->toBeFalse()
        ->and($has(Role::CustomerRepresentative, Permission::CreateQuotations))->toBeFalse();

    // Settings manage (UpdateSettings): Developer/Technician/Sales/External No.
    expect($has(Role::Developer, Permission::UpdateSettings))->toBeFalse()
        ->and($has(Role::Technician, Permission::UpdateSettings))->toBeFalse()
        ->and($has(Role::Sales, Permission::UpdateSettings))->toBeFalse()
        ->and($has(Role::CustomerRepresentative, Permission::UpdateSettings))->toBeFalse();
});
