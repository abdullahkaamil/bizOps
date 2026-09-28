<?php

use App\Actions\Invitations\AcceptInvitation;
use App\Actions\Tenancy\CreateTenant;
use App\Domain\CRM\Models\Customer;
use App\Domain\Settings\TenantSettings;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Department;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;

function umTenant(): Tenant
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

function umOwner(Tenant $tenant): User
{
    return $tenant->run(fn () => User::where('email', 'owner@alpha.test')->first());
}

test('an admin can invite an internal user', function () {
    $tenant = umTenant();
    $owner = umOwner($tenant);

    $this->actingAs($owner)
        ->post('http://alpha.kaamil.test/users/invite', [
            'email' => 'dev@alpha.test', 'user_type' => 'internal', 'role' => Role::Developer->value,
        ])
        ->assertRedirect();

    $tenant->run(function () {
        $invitation = Invitation::where('email', 'dev@alpha.test')->first();
        expect($invitation)->not->toBeNull()
            ->and($invitation->user_type)->toBe(UserType::Internal)
            ->and($invitation->metadata['customer_id'])->toBeNull();
    });
});

test('an admin can invite an external user linked to a customer', function () {
    $tenant = umTenant();
    $owner = umOwner($tenant);
    $customer = $tenant->run(fn () => Customer::create(['company_name' => 'Client Co', 'status' => 'active']));

    $this->actingAs($owner)
        ->post('http://alpha.kaamil.test/users/invite', [
            'email' => 'rep@client.test',
            'user_type' => 'external',
            'role' => Role::CustomerRepresentative->value,
            'customer_id' => $customer->public_id,
        ])
        ->assertRedirect();

    // Accepting the invitation creates an external user tied to the customer.
    $tenant->run(function () use ($customer) {
        $token = null;
        $invitation = Invitation::where('email', 'rep@client.test')->first();
        expect($invitation->metadata['customer_id'])->toBe($customer->id);

        // Re-issue a known token to accept.
        $raw = 'known-token-value';
        $invitation->update(['token_hash' => hash('sha256', $raw)]);
        (new AcceptInvitation)->handle($raw, 'Rep Person', 'password123');

        $user = User::where('email', 'rep@client.test')->first();
        expect($user->user_type)->toBe(UserType::External)
            ->and($user->customer_id)->toBe($customer->id)
            ->and($user->isExternal())->toBeTrue();
    });
});

test('an incompatible role and user type is rejected on invite', function () {
    $tenant = umTenant();
    $owner = umOwner($tenant);

    // Internal user type with the external role.
    $this->actingAs($owner)
        ->post('http://alpha.kaamil.test/users/invite', [
            'email' => 'x@alpha.test', 'user_type' => 'internal', 'role' => Role::CustomerRepresentative->value,
        ])
        ->assertSessionHasErrors('role');

    // External without a customer.
    $this->actingAs($owner)
        ->post('http://alpha.kaamil.test/users/invite', [
            'email' => 'y@alpha.test', 'user_type' => 'external', 'role' => Role::CustomerRepresentative->value,
        ])
        ->assertSessionHasErrors('customer_id');
});

test('assigning an incompatible role to an existing user is rejected', function () {
    $tenant = umTenant();
    $owner = umOwner($tenant);
    $internal = $tenant->run(function () {
        $u = User::factory()->create(['user_type' => 'internal', 'status' => 'active']);
        $u->assignRole(Role::Developer->value);

        return $u;
    });

    $this->actingAs($owner)
        ->patch("http://alpha.kaamil.test/users/{$internal->id}/role", ['role' => Role::CustomerRepresentative->value])
        ->assertSessionHasErrors('role');
});

test('a user can be suspended and reactivated', function () {
    $tenant = umTenant();
    $owner = umOwner($tenant);
    $member = $tenant->run(function () {
        $u = User::factory()->create(['user_type' => 'internal', 'status' => 'active']);
        $u->assignRole(Role::Sales->value);

        return $u;
    });

    $this->actingAs($owner)->post("http://alpha.kaamil.test/users/{$member->id}/suspend")->assertRedirect();
    expect($member->fresh()->status)->toBe(UserStatus::Suspended);

    $this->actingAs($owner)->post("http://alpha.kaamil.test/users/{$member->id}/reactivate")->assertRedirect();
    expect($member->fresh()->status)->toBe(UserStatus::Active);
});

test('the last active owner cannot be suspended', function () {
    $tenant = umTenant();
    $owner = umOwner($tenant);

    $this->actingAs($owner)
        ->post("http://alpha.kaamil.test/users/{$owner->id}/suspend")
        ->assertSessionHasErrors('status');

    expect($owner->fresh()->status)->toBe(UserStatus::Active);
});

test('an admin can assign a department to a user', function () {
    $tenant = umTenant();
    $owner = umOwner($tenant);
    [$department, $member] = $tenant->run(function () {
        $dept = Department::create(['name' => 'Support', 'is_active' => true]);
        $u = User::factory()->create(['user_type' => 'internal', 'status' => 'active']);
        $u->assignRole(Role::Sales->value);

        return [$dept, $u];
    });

    $this->actingAs($owner)
        ->post("http://alpha.kaamil.test/users/{$member->id}/department", ['department_id' => $department->public_id])
        ->assertRedirect();

    expect($member->fresh()->department_id)->toBe($department->id);
});

test('external users cannot access employee management', function () {
    $tenant = umTenant();
    $external = $tenant->run(function () {
        $u = User::factory()->create(['user_type' => 'external', 'status' => 'active']);
        $u->assignRole(Role::CustomerRepresentative->value);

        return $u;
    });

    $this->actingAs($external)->get('http://alpha.kaamil.test/users')->assertForbidden();
});

test('inviting a user surfaces a copyable accept link when auto-accept is off', function () {
    $tenant = umTenant();
    $owner = umOwner($tenant);

    $this->actingAs($owner)
        ->post('http://alpha.kaamil.test/users/invite', [
            'email' => 'dev@alpha.test', 'user_type' => 'internal', 'role' => Role::Developer->value,
        ])
        ->assertRedirect()
        ->assertSessionHas('invite_result', fn (array $r): bool => $r['mode'] === 'link'
            && $r['email'] === 'dev@alpha.test'
            && str_contains((string) $r['link'], '/invitations/'));

    // The invitation exists but no user has been created yet.
    $tenant->run(function () {
        expect(Invitation::where('email', 'dev@alpha.test')->exists())->toBeTrue()
            ->and(User::where('email', 'dev@alpha.test')->exists())->toBeFalse();
    });
});

test('with auto-accept on, inviting creates an active user and returns a temp password', function () {
    $tenant = umTenant();
    $owner = umOwner($tenant);
    $tenant->run(fn () => app(TenantSettings::class)->set('invitations', 'auto_accept', true));

    $this->actingAs($owner)
        ->post('http://alpha.kaamil.test/users/invite', [
            'email' => 'auto@alpha.test', 'user_type' => 'internal', 'role' => Role::Developer->value,
        ])
        ->assertRedirect()
        ->assertSessionHas('invite_result', fn (array $r): bool => $r['mode'] === 'auto_accepted'
            && $r['email'] === 'auto@alpha.test'
            && strlen((string) $r['password']) >= 12);

    $tenant->run(function () {
        $user = User::where('email', 'auto@alpha.test')->first();
        expect($user)->not->toBeNull()
            ->and($user->status)->toBe(UserStatus::Active)
            ->and($user->hasRole(Role::Developer->value))->toBeTrue();

        // The invitation was consumed.
        expect(Invitation::where('email', 'auto@alpha.test')->first()->accepted_at)->not->toBeNull();
    });
});

test('the auto-accept setting persists through company settings', function () {
    $tenant = umTenant();
    $owner = umOwner($tenant);

    $this->actingAs($owner)
        ->put('http://alpha.kaamil.test/settings/company', [
            'timezone' => 'UTC', 'currency' => 'USD', 'auto_accept_invitations' => '1',
        ])
        ->assertRedirect();

    expect($tenant->run(fn (): bool => app(TenantSettings::class)->autoAcceptsInvitations()))->toBeTrue();

    // Omitting the checkbox turns it back off.
    $this->actingAs($owner)
        ->put('http://alpha.kaamil.test/settings/company', ['timezone' => 'UTC', 'currency' => 'USD'])
        ->assertRedirect();

    expect($tenant->run(fn (): bool => app(TenantSettings::class)->autoAcceptsInvitations()))->toBeFalse();
});

test('the team page exposes roles tagged by user type for filtered dropdowns', function () {
    $tenant = umTenant();
    $owner = umOwner($tenant);
    $tenant->run(fn () => Spatie\Permission\Models\Role::create(['name' => 'field_supervisor', 'guard_name' => 'web']));

    $this->actingAs($owner)
        ->get('http://alpha.kaamil.test/users')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('tenant/users/Index')
            // The external role is tagged external; internal + custom roles are internal.
            ->where('roles', fn ($roles) => collect($roles)->firstWhere('name', 'customer_representative')['user_type'] === 'external'
                && collect($roles)->firstWhere('name', 'field_supervisor')['user_type'] === 'internal'
                && collect($roles)->firstWhere('name', 'manager')['user_type'] === 'internal'));
});
