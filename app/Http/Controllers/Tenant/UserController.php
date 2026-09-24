<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Invitations\AcceptInvitation;
use App\Actions\Invitations\CreateInvitation;
use App\Domain\Authorization\RoleCatalog;
use App\Domain\CRM\Models\Customer;
use App\Domain\Notifications\Notifications\UserInvitedNotification;
use App\Domain\Settings\TenantSettings;
use App\Enums\Role as RoleEnum;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\InviteUserRequest;
use App\Http\Requests\Tenant\StoreUserRequest;
use App\Models\Department;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(protected TenantSettings $settings) {}

    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with(['roles:id,name', 'department:id,public_id,name', 'customer:id,public_id,company_name'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'user_type' => $user->user_type->value,
                'status' => $user->status->value,
                'roles' => $user->roles->pluck('name')->all(),
                'department' => $user->department?->name,
                'customer' => $user->customer?->company_name,
            ]);

        return Inertia::render('tenant/users/Index', [
            'internalUsers' => $users->where('user_type', UserType::Internal->value)->values(),
            'externalUsers' => $users->where('user_type', UserType::External->value)->values(),
            'roles' => RoleCatalog::names()
                ->map(fn (string $name): array => ['name' => $name, 'user_type' => RoleCatalog::userTypeFor($name)->value])
                ->all(),
            'departments' => Department::query()->orderBy('name')->get(['public_id', 'name'])
                ->map(fn (Department $d): array => ['id' => $d->public_id, 'name' => $d->name]),
            'customers' => Customer::query()->orderBy('company_name')->get(['public_id', 'company_name'])
                ->map(fn (Customer $c): array => ['id' => $c->public_id, 'name' => $c->company_name]),
            'invitations' => Invitation::query()->whereNull('accepted_at')->latest()->get()
                ->map(fn (Invitation $i): array => [
                    'id' => $i->id,
                    'email' => $i->email,
                    'user_type' => $i->user_type->value,
                    'role' => $i->role,
                    'expired' => $i->expires_at->isPast(),
                ]),
            'autoAcceptInvitations' => $this->settings->autoAcceptsInvitations(),
            // One-time result of the last invite/copy-link action (link or
            // temporary credentials to relay manually — surfaced because there
            // may be no mail server to deliver it).
            'inviteResult' => session('invite_result'),
        ]);
    }

    /**
     * Directly create an internal user (external users must be invited).
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $roleName = $request->string('role')->toString();

        $user = User::create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => Hash::make($request->string('password')->toString()),
            'user_type' => RoleCatalog::userTypeFor($roleName)->value,
            'email_verified_at' => now(),
        ]);

        $user->assignRole($roleName);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('tenant.users.index');
    }

    /**
     * Invite an internal or external user. External invitations require a customer.
     *
     * With auto-accept enabled the account is created immediately and a temporary
     * password is returned to relay manually; otherwise a copyable accept link is
     * returned (and the email is attempted best-effort). Either way the result is
     * surfaced in the UI because there may be no mail server to deliver it.
     */
    public function invite(InviteUserRequest $request, CreateInvitation $create, AcceptInvitation $accept): RedirectResponse
    {
        $this->authorize('create', User::class);

        $customer = $request->filled('customer_id')
            ? Customer::where('public_id', $request->input('customer_id'))->firstOrFail()
            : null;

        $department = $request->filled('department_id')
            ? Department::where('public_id', $request->input('department_id'))->firstOrFail()
            : null;

        $email = $request->string('email')->toString();
        $role = $request->string('role')->toString();

        $result = $create->handle(
            email: $email,
            userType: UserType::from($request->string('user_type')->toString()),
            role: $role,
            invitedBy: $request->user(),
            customerId: $customer?->id,
            departmentId: $department?->id,
        );

        if ($this->settings->autoAcceptsInvitations()) {
            $password = Str::password(12, symbols: false);
            $name = Str::of($email)->before('@')->headline()->toString();

            $accept->handle($result['token'], $name, $password);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('User created and invitation accepted.')]);

            return to_route('tenant.users.index')->with('invite_result', [
                'mode' => 'auto_accepted',
                'email' => $email,
                'name' => $name,
                'password' => $password,
            ]);
        }

        $acceptUrl = route('tenant.invitations.accept', ['token' => $result['token']]);
        $this->sendInvitationEmail($email, $acceptUrl, $role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation created.')]);

        return to_route('tenant.users.index')->with('invite_result', [
            'mode' => 'link',
            'email' => $email,
            'link' => $acceptUrl,
        ]);
    }

    /**
     * Regenerate a pending invitation's token and surface a fresh accept link to
     * copy (the email is attempted best-effort).
     */
    public function resendInvitation(Invitation $invitation): RedirectResponse
    {
        $this->authorize('create', User::class);

        $token = Str::random(48);
        $invitation->update([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ]);

        $acceptUrl = route('tenant.invitations.accept', ['token' => $token]);
        $this->sendInvitationEmail($invitation->email, $acceptUrl, (string) $invitation->role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation link regenerated.')]);

        return to_route('tenant.users.index')->with('invite_result', [
            'mode' => 'link',
            'email' => $invitation->email,
            'link' => $acceptUrl,
        ]);
    }

    /**
     * Send the (email-only) invitation notification to a not-yet-user address.
     * Best-effort: a mail failure must never break the invite — the accept link
     * is always surfaced in the UI to relay manually.
     */
    protected function sendInvitationEmail(string $email, string $acceptUrl, string $role): void
    {
        try {
            Notification::route('mail', $email)
                ->notify(new UserInvitedNotification($acceptUrl, $role));
        } catch (\Throwable $e) {
            Log::warning('Invitation email could not be sent.', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function suspend(User $user): RedirectResponse
    {
        $this->authorize('suspend', $user);

        if ($this->isLastActiveOwner($user)) {
            throw ValidationException::withMessages(['status' => __('The last active owner cannot be suspended.')]);
        }

        $user->update(['status' => UserStatus::Suspended]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User suspended.')]);

        return to_route('tenant.users.index');
    }

    public function reactivate(User $user): RedirectResponse
    {
        $this->authorize('suspend', $user);

        $user->update(['status' => UserStatus::Active]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User reactivated.')]);

        return to_route('tenant.users.index');
    }

    public function updateRole(User $user): RedirectResponse
    {
        $this->authorize('manageRoles', User::class);

        // Only roles compatible with this user's type are assignable (external
        // users stay on the fixed external role; internal users get any internal
        // role, system or custom).
        $validated = request()->validate([
            'role' => ['required', 'string', Rule::in(RoleCatalog::assignableFor($user->user_type))],
        ]);

        $roleName = $validated['role'];

        if ($roleName !== RoleEnum::Owner->value && $this->isLastActiveOwner($user)) {
            throw ValidationException::withMessages(['role' => __('The last owner cannot be demoted.')]);
        }

        $previous = $user->getRoleNames()->first();
        $user->syncRoles([$roleName]);

        activity('user')->performedOn($user)->causedBy(request()->user())
            ->withProperties(['from' => $previous, 'to' => $roleName])
            ->event('role_changed')->log('user.role_changed');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('tenant.users.index');
    }

    public function assignDepartment(User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = request()->validate([
            'department_id' => ['nullable', Rule::exists('departments', 'public_id')],
        ]);

        $department = $validated['department_id'] !== null
            ? Department::where('public_id', $validated['department_id'])->first()
            : null;

        $user->update(['department_id' => $department?->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department updated.')]);

        return to_route('tenant.users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User removed.')]);

        return to_route('tenant.users.index');
    }

    /**
     * Whether this user is the only remaining active owner of the tenant.
     */
    protected function isLastActiveOwner(User $user): bool
    {
        if (! $user->isOwner()) {
            return false;
        }

        return User::role(RoleEnum::Owner->value)
            ->where('status', UserStatus::Active->value)
            ->count() <= 1;
    }
}
