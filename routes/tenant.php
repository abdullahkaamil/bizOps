<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Routes that should ONLY exist inside a tenant (subdomain) context. Tenancy
| is already initialized globally by the InitializeTenancyBySubdomain
| middleware on the web group (see bootstrap/app.php), so here we only need to
| guarantee the route is unreachable from the central domain via the `tenant`
| middleware. The shared tenant application (dashboard, settings, auth) lives
| in routes/web.php and works in both contexts.
|
*/

Route::middleware(['web', 'tenant'])->group(function () {
    Route::get('/tenant/context', function () {
        return response()->json([
            'tenant_id' => tenant('id'),
            'tenant_name' => tenant('name'),
        ]);
    })->name('tenant.context');

    // Shown when the tenant's license has expired. Not license-gated so the
    // notice itself remains reachable (see EnsureTenantLicenseIsActive).
    Route::get('/license-expired', function () {
        return Inertia::render('tenant/LicenseExpired', [
            'expiredAt' => tenant()->license_expires_at?->toIso8601String(),
        ]);
    })->name('tenant.license.expired');

    Route::middleware(['auth', 'verified'])
        ->name('tenant.')
        ->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role.update');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        });
});
