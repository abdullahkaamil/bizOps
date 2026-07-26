<?php

use App\Http\Controllers\Admin\TenantController;
use Illuminate\Support\Facades\Route;

/*
| Central SaaS administration console. Served on a dedicated admin subdomain
| (e.g. dashboard.kaamil.test) which is registered as a central domain, so
| tenancy is never initialized here. The domain constraint keeps these routes
| off the public site and tenant subdomains.
*/
Route::domain(config('tenancy.admin_domain'))
    ->name('admin.')
    ->group(function () {
        Route::redirect('/', '/tenants');

        Route::middleware(['auth', 'verified'])->group(function () {
            Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
            Route::get('tenants/create', [TenantController::class, 'create'])->name('tenants.create');
            Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
            Route::post('tenants/{tenant}/renew', [TenantController::class, 'renew'])->name('tenants.renew');
            Route::delete('tenants/{tenant}', [TenantController::class, 'destroy'])->name('tenants.destroy');
        });
    });

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
