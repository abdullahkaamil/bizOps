<?php

use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\SearchController;
use Illuminate\Support\Facades\Route;

/*
| The admin console lives at dashboard.kaamil.test; its root redirects into the
| tenant list. (The CRUD routes below are registered with RELATIVE paths so
| their links stay same-origin — see note there.)
*/
Route::domain(config('tenancy.admin_domain'))->group(function () {
    Route::redirect('/', '/admin/tenants');
});

/*
| Central SaaS administration. Guarded by the `central` middleware (404s on
| tenant subdomains) rather than a hard domain constraint, so Wayfinder emits
| relative URLs. This keeps admin links same-origin on whichever central domain
| the operator is on (kaamil.test or dashboard.kaamil.test), avoiding the
| cross-origin XHR/CORS failure that a domain-locked absolute URL causes.
*/
Route::middleware(['central', 'auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('tenants/create', [TenantController::class, 'create'])->name('tenants.create');
        Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
        Route::post('tenants/{tenant}/retry', [TenantController::class, 'retry'])->name('tenants.retry');
        Route::post('tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');
        Route::post('tenants/{tenant}/reactivate', [TenantController::class, 'reactivate'])->name('tenants.reactivate');
        Route::post('tenants/{tenant}/renew', [TenantController::class, 'renew'])->name('tenants.renew');
        Route::put('tenants/{tenant}/plan', [TenantController::class, 'updatePlan'])->name('tenants.plan');
        Route::put('tenants/{tenant}/features', [TenantController::class, 'updateFeatures'])->name('tenants.features');
        Route::post('tenants/{tenant}/archive', [TenantController::class, 'archive'])->name('tenants.archive');
        Route::post('tenants/{tenant}/request-deletion', [TenantController::class, 'requestDeletion'])->name('tenants.request-deletion');
        Route::post('tenants/{tenant}/purge', [TenantController::class, 'purge'])->name('tenants.purge');
        Route::post('tenants/{tenant}/support', [TenantController::class, 'support'])->name('tenants.support');
        Route::delete('tenants/{tenant}', [TenantController::class, 'destroy'])->name('tenants.destroy');

        Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    });

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('search', [SearchController::class, 'index'])->name('search');
    Route::get('search/results', [SearchController::class, 'results'])->name('search.results');
});

require __DIR__.'/settings.php';
