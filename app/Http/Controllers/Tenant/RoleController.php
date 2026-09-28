<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Authorization\RoleCatalog;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreRoleRequest;
use App\Http\Requests\Tenant\UpdateRoleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

/**
 * Manage tenant roles. Built-in ("system") roles from the Role enum are shown but
 * locked; custom roles are created, re-permissioned and deleted here. Custom roles
 * are internal-only (RoleCatalog), so the external trust boundary is unaffected.
 * Gated by roles.manage (UserPolicy@manageRoles) and internal-only via the route.
 */
class RoleController extends Controller
{
    public function index(): Response
    {
        $this->authorize('manageRoles', User::class);

        $roles = SpatieRole::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (SpatieRole $role): array => [
                'name' => $role->name,
                'is_system' => RoleCatalog::isSystem($role->name),
                'user_type' => RoleCatalog::userTypeFor($role->name)->value,
                'permissions' => $role->permissions->pluck('name')->all(),
                'users_count' => $role->users_count,
            ])->all();

        return Inertia::render('tenant/roles/Index', [
            'roles' => $roles,
            'permissionGroups' => Permission::grouped(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->authorize('manageRoles', User::class);

        $name = Str::slug($request->string('name')->toString(), '_');

        if ($name === '') {
            return back()->withErrors(['name' => __('Enter a valid role name.')]);
        }

        if (SpatieRole::where('name', $name)->exists()) {
            return back()->withErrors(['name' => __('A role with this name already exists.')]);
        }

        App::make(PermissionRegistrar::class)->forgetCachedPermissions();

        // Custom roles are internal-only, guard web (matching the seeded roles).
        $role = SpatieRole::create(['name' => $name, 'guard_name' => 'web']);
        $role->syncPermissions($request->validated('permissions', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('tenant.roles.index');
    }

    public function update(UpdateRoleRequest $request, string $role): RedirectResponse
    {
        $this->authorize('manageRoles', User::class);

        $model = SpatieRole::where('name', $role)->firstOrFail();

        if (RoleCatalog::isSystem($model->name)) {
            return back()->withErrors(['role' => __('Built-in roles cannot be changed.')]);
        }

        App::make(PermissionRegistrar::class)->forgetCachedPermissions();

        $model->syncPermissions($request->validated('permissions', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('tenant.roles.index');
    }

    public function destroy(string $role): RedirectResponse
    {
        $this->authorize('manageRoles', User::class);

        $model = SpatieRole::where('name', $role)->firstOrFail();

        if (RoleCatalog::isSystem($model->name)) {
            return back()->withErrors(['role' => __('Built-in roles cannot be deleted.')]);
        }

        if ($model->users()->count() > 0) {
            return back()->withErrors(['role' => __('Reassign the users on this role before deleting it.')]);
        }

        $model->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('tenant.roles.index');
    }
}
