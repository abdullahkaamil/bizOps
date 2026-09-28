<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        // Managing departments is part of managing the team.
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
        ]);

        Department::create([...$validated, 'is_active' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department created.')]);

        return to_route('tenant.users.index');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->authorize('create', User::class);

        $department->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department removed.')]);

        return to_route('tenant.users.index');
    }
}
