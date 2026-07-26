<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * List the tenant's users.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->all(),
            ]);

        return Inertia::render('tenant/users/Index', [
            'users' => $users,
            'roles' => RoleEnum::values(),
        ]);
    }

    /**
     * Create a new tenant user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $user = User::create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => Hash::make($request->string('password')->toString()),
            'email_verified_at' => now(),
        ]);

        $user->assignRole($request->string('role')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('tenant.users.index');
    }

    /**
     * Update a user's role.
     */
    public function updateRole(User $user): RedirectResponse
    {
        $this->authorize('manageRoles', User::class);

        $validated = request()->validate([
            'role' => ['required', 'string', Rule::in(RoleEnum::values())],
        ]);

        $user->syncRoles([$validated['role']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('tenant.users.index');
    }

    /**
     * Delete a tenant user.
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User removed.')]);

        return to_route('tenant.users.index');
    }
}
