<?php

use App\Actions\Invitations\AcceptInvitation;
use App\Actions\Invitations\CreateInvitation;
use App\Actions\Tenancy\CreateTenant;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

function authTenant(string $slug): Tenant
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

test('a user logs into the correct tenant', function () {
    authTenant('alpha');

    $this->post('http://alpha.kaamil.test/login', [
        'email' => 'owner@alpha.test',
        'password' => 'password123',
    ]);

    $this->assertAuthenticated();
});

test('the same email can exist in two different tenants', function () {
    $alpha = authTenant('alpha');
    $beta = authTenant('beta');

    $alpha->run(fn () => User::factory()->create(['email' => 'same@x.test', 'password' => Hash::make('secret123')]));
    $beta->run(fn () => User::factory()->create(['email' => 'same@x.test', 'password' => Hash::make('secret123')]));

    $alpha->run(fn () => expect(User::where('email', 'same@x.test')->count())->toBe(1));
    $beta->run(fn () => expect(User::where('email', 'same@x.test')->count())->toBe(1));

    $this->post('http://alpha.kaamil.test/login', ['email' => 'same@x.test', 'password' => 'secret123']);
    $this->assertAuthenticated();
});

test('an alpha user cannot authenticate on beta', function () {
    authTenant('alpha');
    authTenant('beta');

    $this->post('http://beta.kaamil.test/login', [
        'email' => 'owner@alpha.test',
        'password' => 'password123',
    ]);

    $this->assertGuest();
});

test('a central user cannot authenticate through the tenant guard', function () {
    authTenant('alpha');
    User::factory()->create(['email' => 'central@x.test', 'password' => Hash::make('secret123')]);

    $this->post('http://alpha.kaamil.test/login', [
        'email' => 'central@x.test',
        'password' => 'secret123',
    ]);

    $this->assertGuest();
});

test('a suspended user cannot log in', function () {
    $tenant = authTenant('alpha');

    $tenant->run(function () {
        User::where('email', 'owner@alpha.test')->first()->update(['status' => UserStatus::Suspended]);
    });

    $this->post('http://alpha.kaamil.test/login', [
        'email' => 'owner@alpha.test',
        'password' => 'password123',
    ]);

    $this->assertGuest();
});

test('an expired invitation cannot be accepted', function () {
    $tenant = authTenant('alpha');

    $tenant->run(function () {
        $token = (new CreateInvitation)->handle('invitee@alpha.test', UserType::Internal, 'sales', null, -1)['token'];

        expect(fn () => (new AcceptInvitation)->handle($token, 'Invitee', 'password123'))
            ->toThrow(ValidationException::class);
    });
});

test('an already-accepted invitation cannot be reused', function () {
    $tenant = authTenant('alpha');

    $tenant->run(function () {
        $token = (new CreateInvitation)->handle('invitee@alpha.test', UserType::Internal, 'sales')['token'];

        (new AcceptInvitation)->handle($token, 'Invitee', 'password123');

        expect(fn () => (new AcceptInvitation)->handle($token, 'Again', 'password123'))
            ->toThrow(ValidationException::class);
    });
});

test('accepting an invitation creates an active tenant user', function () {
    $tenant = authTenant('alpha');
    $token = $tenant->run(fn () => (new CreateInvitation)->handle('invitee@alpha.test', UserType::Internal, 'sales')['token']);

    $this->post("http://alpha.kaamil.test/invitations/{$token}/accept", [
        'name' => 'New Member',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect();

    $tenant->run(function () {
        $user = User::where('email', 'invitee@alpha.test')->first();
        expect($user)->not->toBeNull()
            ->and($user->status)->toBe(UserStatus::Active)
            ->and($user->hasRole('sales'))->toBeTrue();
    });

    // The invitation can now be logged in with.
    $this->post('http://alpha.kaamil.test/login', ['email' => 'invitee@alpha.test', 'password' => 'password123']);
    $this->assertAuthenticated();
});

test('password reset is tenant-aware', function () {
    $tenant = authTenant('alpha');

    $this->post('http://alpha.kaamil.test/forgot-password', ['email' => 'owner@alpha.test'])
        ->assertSessionHasNoErrors();

    $tenant->run(fn () => expect(DB::table('password_reset_tokens')->where('email', 'owner@alpha.test')->exists())->toBeTrue());
});

test('session cookies are host-scoped so they cannot cross tenants', function () {
    // Host-only cookies (no shared parent domain) mean an alpha.kaamil.test
    // session cookie is never sent to beta.kaamil.test; combined with
    // database-per-tenant sessions, a session id is only valid in the tenant DB
    // that created it.
    expect(config('session.domain'))->toBeNull();
});
