<?php

use App\Actions\Tenancy\CreateTenant;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role as SpatieRole;

function rmTenant(): Tenant
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

function rmOwner(Tenant $tenant): User
{
    return $tenant->run(fn () => User::where('email', 'owner@alpha.test')->first());
}

$base = 'http://alpha.kaamil.test';

test('an internal manager can create a custom role with permissions', function () use ($base) {
    $tenant = rmTenant();
    $owner = rmOwner($tenant);

    $this->actingAs($owner)
        ->post("{$base}/roles", [
            'name' => 'Field Supervisor',
            'permissions' => [Permission::ViewTasks->value, Permission::ViewJobs->value],
        ])
        ->assertRedirect();

    $tenant->run(function () {
        $role = SpatieRole::where('name', 'field_supervisor')->first();
        expect($role)->not->toBeNull()
            ->and($role->permissions->pluck('name')->sort()->values()->all())
            ->toBe([Permission::ViewJobs->value, Permission::ViewTasks->value]);
    });
});

test('a custom role can be assigned to an internal user', function () use ($base) {
    $tenant = rmTenant();
    $owner = rmOwner($tenant);

    $member = $tenant->run(function () {
        SpatieRole::create(['name' => 'field_supervisor', 'guard_name' => 'web'])
            ->syncPermissions([Permission::ViewTasks->value]);

        $u = User::factory()->create(['user_type' => 'internal', 'status' => 'active']);
        $u->assignRole(Role::Developer->value);

        return $u;
    });

    $this->actingAs($owner)
        ->patch("{$base}/users/{$member->id}/role", ['role' => 'field_supervisor'])
        ->assertRedirect();

    expect($tenant->run(fn (): bool => $member->fresh()->hasRole('field_supervisor')))->toBeTrue();
});

test('a custom role permissions can be edited', function () use ($base) {
    $tenant = rmTenant();
    $owner = rmOwner($tenant);
    $tenant->run(fn () => SpatieRole::create(['name' => 'field_supervisor', 'guard_name' => 'web']));

    $this->actingAs($owner)
        ->put("{$base}/roles/field_supervisor", ['permissions' => [Permission::ViewInventory->value]])
        ->assertRedirect();

    expect($tenant->run(fn () => SpatieRole::where('name', 'field_supervisor')->first()->permissions->pluck('name')->all()))
        ->toBe([Permission::ViewInventory->value]);
});

test('built-in roles cannot be edited or deleted', function () use ($base) {
    $tenant = rmTenant();
    $owner = rmOwner($tenant);

    $this->actingAs($owner)
        ->put("{$base}/roles/manager", ['permissions' => []])
        ->assertSessionHasErrors('role');

    $this->actingAs($owner)
        ->delete("{$base}/roles/manager")
        ->assertSessionHasErrors('role');

    // The manager role still holds its seeded permissions.
    expect($tenant->run(fn (): int => SpatieRole::where('name', 'manager')->first()->permissions()->count()))
        ->toBeGreaterThan(0);
});

test('a custom role in use cannot be deleted, but an empty one can', function () use ($base) {
    $tenant = rmTenant();
    $owner = rmOwner($tenant);

    $tenant->run(function () {
        SpatieRole::create(['name' => 'in_use', 'guard_name' => 'web']);
        SpatieRole::create(['name' => 'unused', 'guard_name' => 'web']);

        $u = User::factory()->create(['user_type' => 'internal', 'status' => 'active']);
        $u->assignRole('in_use');
    });

    // Assigned role is protected.
    $this->actingAs($owner)->delete("{$base}/roles/in_use")->assertSessionHasErrors('role');
    expect($tenant->run(fn (): bool => SpatieRole::where('name', 'in_use')->exists()))->toBeTrue();

    // Empty role deletes.
    $this->actingAs($owner)->delete("{$base}/roles/unused")->assertRedirect();
    expect($tenant->run(fn (): bool => SpatieRole::where('name', 'unused')->exists()))->toBeFalse();
});

test('a custom role is internal-only and cannot be assigned to an external user', function () use ($base) {
    $tenant = rmTenant();
    $owner = rmOwner($tenant);

    $external = $tenant->run(function () {
        SpatieRole::create(['name' => 'field_supervisor', 'guard_name' => 'web']);

        $u = User::factory()->create(['user_type' => 'external', 'status' => 'active']);
        $u->assignRole(Role::CustomerRepresentative->value);

        return $u;
    });

    $this->actingAs($owner)
        ->patch("{$base}/users/{$external->id}/role", ['role' => 'field_supervisor'])
        ->assertSessionHasErrors('role');
});

test('external representatives cannot reach the roles page', function () use ($base) {
    $tenant = rmTenant();

    $external = $tenant->run(function () {
        $u = User::factory()->create(['user_type' => 'external', 'status' => 'active']);
        $u->assignRole(Role::CustomerRepresentative->value);

        return $u;
    });

    $this->actingAs($external)->get("{$base}/roles")->assertForbidden();
});

test('the roles page lists system and custom roles', function () use ($base) {
    $tenant = rmTenant();
    $owner = rmOwner($tenant);
    $tenant->run(fn () => SpatieRole::create(['name' => 'field_supervisor', 'guard_name' => 'web']));

    $this->actingAs($owner)
        ->get("{$base}/roles")
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('tenant/roles/Index')
            ->has('permissionGroups')
            ->where('roles', fn ($roles) => collect($roles)->contains('name', 'field_supervisor')
                && collect($roles)->contains('name', 'manager')));
});
