<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\AcceptInvitationController;
use App\Http\Controllers\Tenant\BoardColumnController;
use App\Http\Controllers\Tenant\BoardController;
use App\Http\Controllers\Tenant\BoardMemberController;
use App\Http\Controllers\Tenant\CompanySettingsController;
use App\Http\Controllers\Tenant\CustomerAddressController;
use App\Http\Controllers\Tenant\CustomerContactController;
use App\Http\Controllers\Tenant\CustomerController;
use App\Http\Controllers\Tenant\DepartmentController;
use App\Http\Controllers\Tenant\GeneratedDocumentController;
use App\Http\Controllers\Tenant\InventoryController;
use App\Http\Controllers\Tenant\JobController;
use App\Http\Controllers\Tenant\JobImageController;
use App\Http\Controllers\Tenant\JobSignatureController;
use App\Http\Controllers\Tenant\JobTransitionController;
use App\Http\Controllers\Tenant\PublicQuotationController;
use App\Http\Controllers\Tenant\QuotationController;
use App\Http\Controllers\Tenant\QuotationTransitionController;
use App\Http\Controllers\Tenant\RoleController;
use App\Http\Controllers\Tenant\SupplierController;
use App\Http\Controllers\Tenant\TaskAttachmentController;
use App\Http\Controllers\Tenant\TaskCommentController;
use App\Http\Controllers\Tenant\TaskController;
use App\Http\Controllers\Tenant\UserController;
use App\Http\Controllers\Tenant\WorkshopAttachmentController;
use App\Http\Controllers\Tenant\WorkshopController;
use App\Http\Controllers\Tenant\WorkshopPartController;
use App\Http\Controllers\Tenant\WorkshopTransitionController;
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

    // Accept a tenant invitation (guest flow; token is the raw invite token).
    Route::get('invitations/{token}/accept', [AcceptInvitationController::class, 'show'])->name('tenant.invitations.accept');
    Route::post('invitations/{token}/accept', [AcceptInvitationController::class, 'store'])->name('tenant.invitations.accept.store');

    // Public quotation review (guest; the customer accepts/rejects via a signed
    // link — no login). The `signed` middleware is the gate.
    Route::middleware('signed')->group(function () {
        Route::get('quotations/{quotation}/review', [PublicQuotationController::class, 'show'])->name('tenant.quotations.review');
        Route::post('quotations/{quotation}/review/accept', [PublicQuotationController::class, 'accept'])->name('tenant.quotations.review.accept');
        Route::post('quotations/{quotation}/review/reject', [PublicQuotationController::class, 'reject'])->name('tenant.quotations.review.reject');
    });

    // Internal-only tenant application (external customer reps are forbidden).
    Route::middleware(['auth', 'verified', 'internal'])
        ->name('tenant.')
        ->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::post('users/invite', [UserController::class, 'invite'])->name('users.invite');
            Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->name('users.role.update');
            Route::post('users/{user}/department', [UserController::class, 'assignDepartment'])->name('users.department');
            Route::post('users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
            Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

            Route::post('invitations/{invitation}/resend', [UserController::class, 'resendInvitation'])->name('invitations.resend');
            Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
            Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

            // Roles administration (built-in roles are read-only; custom roles CRUD).
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
            Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

            Route::get('settings/company', [CompanySettingsController::class, 'edit'])->name('settings.company');
            Route::put('settings/company', [CompanySettingsController::class, 'update'])->name('settings.company.update');

            // CRM — customers with nested contacts and addresses.
            Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
            Route::get('customers/create', [CustomerController::class, 'create'])->name('customers.create');
            Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
            Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
            Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
            Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
            Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

            Route::scopeBindings()->group(function () {
                Route::post('customers/{customer}/contacts', [CustomerContactController::class, 'store'])->name('customers.contacts.store');
                Route::put('customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'update'])->name('customers.contacts.update');
                Route::post('customers/{customer}/contacts/{contact}/primary', [CustomerContactController::class, 'setPrimary'])->name('customers.contacts.primary');
                Route::delete('customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'destroy'])->name('customers.contacts.destroy');

                Route::post('customers/{customer}/addresses', [CustomerAddressController::class, 'store'])->name('customers.addresses.store');
                Route::put('customers/{customer}/addresses/{address}', [CustomerAddressController::class, 'update'])->name('customers.addresses.update');
                Route::post('customers/{customer}/addresses/{address}/primary', [CustomerAddressController::class, 'setPrimary'])->name('customers.addresses.primary');
                Route::delete('customers/{customer}/addresses/{address}', [CustomerAddressController::class, 'destroy'])->name('customers.addresses.destroy');
            });

            // Field-service jobs (internal only; the mobile technician workflow).
            Route::get('jobs', [JobController::class, 'index'])->name('jobs.index');
            Route::get('jobs/create', [JobController::class, 'create'])->name('jobs.create');
            Route::post('jobs', [JobController::class, 'store'])->name('jobs.store');
            Route::get('jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
            Route::put('jobs/{job}', [JobController::class, 'update'])->name('jobs.update');
            Route::put('jobs/{job}/service-data', [JobController::class, 'updateServiceData'])->name('jobs.service-data');

            // Explicit transitions — no generic status setter.
            Route::post('jobs/{job}/start', [JobTransitionController::class, 'start'])->name('jobs.start');
            Route::post('jobs/{job}/complete', [JobTransitionController::class, 'complete'])->name('jobs.complete');
            Route::post('jobs/{job}/cancel', [JobTransitionController::class, 'cancel'])->name('jobs.cancel');
            Route::post('jobs/{job}/reopen', [JobTransitionController::class, 'reopen'])->name('jobs.reopen');
            Route::post('jobs/{job}/assign', [JobTransitionController::class, 'assign'])->name('jobs.assign');

            Route::post('jobs/{job}/images', [JobImageController::class, 'store'])->name('jobs.images.store');
            Route::delete('job-images/{image}', [JobImageController::class, 'destroy'])->name('jobs.images.destroy');
            Route::get('job-images/{image}/download', [JobImageController::class, 'download'])->name('jobs.images.download');

            Route::post('jobs/{job}/signature', [JobSignatureController::class, 'store'])->name('jobs.signature.store');
            Route::get('job-signatures/{signature}/download', [JobSignatureController::class, 'download'])->name('jobs.signature.download');

            Route::post('jobs/{job}/report', [JobController::class, 'generateReport'])->name('jobs.report');

            // Workshop & device registry.
            Route::get('workshop', [WorkshopController::class, 'index'])->name('workshop.index');
            Route::get('workshop/create', [WorkshopController::class, 'create'])->name('workshop.create');
            Route::get('workshop/device-lookup', [WorkshopController::class, 'deviceLookup'])->name('workshop.device-lookup');
            Route::post('workshop', [WorkshopController::class, 'store'])->name('workshop.store');
            Route::get('workshop/{ticket}', [WorkshopController::class, 'show'])->name('workshop.show');
            Route::put('workshop/{ticket}/repair-notes', [WorkshopController::class, 'updateRepairNotes'])->name('workshop.repair-notes');

            Route::post('workshop/{ticket}/complete', [WorkshopTransitionController::class, 'complete'])->name('workshop.complete');
            Route::post('workshop/{ticket}/deliver', [WorkshopTransitionController::class, 'deliver'])->name('workshop.deliver');
            Route::post('workshop/{ticket}/link', [WorkshopTransitionController::class, 'link'])->name('workshop.link');
            Route::post('workshop/{ticket}/unlink', [WorkshopTransitionController::class, 'unlink'])->name('workshop.unlink');

            Route::post('workshop/{ticket}/attachments', [WorkshopAttachmentController::class, 'store'])->name('workshop.attachments.store');
            Route::delete('workshop-attachments/{attachment}', [WorkshopAttachmentController::class, 'destroy'])->name('workshop.attachments.destroy');
            Route::get('workshop-attachments/{attachment}/download', [WorkshopAttachmentController::class, 'download'])->name('workshop.attachments.download');

            Route::post('workshop/{ticket}/parts', [WorkshopPartController::class, 'store'])->name('workshop.parts.store');
            Route::delete('workshop-parts/{part}', [WorkshopPartController::class, 'destroy'])->name('workshop.parts.destroy');

            // Inventory & suppliers.
            Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
            Route::get('inventory/create', [InventoryController::class, 'create'])->name('inventory.create');
            Route::post('inventory', [InventoryController::class, 'store'])->name('inventory.store');
            Route::get('inventory/{item}', [InventoryController::class, 'show'])->name('inventory.show');
            Route::put('inventory/{item}', [InventoryController::class, 'update'])->name('inventory.update');
            Route::post('inventory/{item}/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
            Route::post('inventory/{item}/suppliers', [InventoryController::class, 'attachSupplier'])->name('inventory.suppliers.attach');

            Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
            Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
            Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
            Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
            Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

            // Quotations — gated by the tenant's `quotations` feature flag.
            Route::middleware('feature:quotations')->group(function () {
                Route::get('quotations', [QuotationController::class, 'index'])->name('quotations.index');
                Route::get('quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
                Route::post('quotations', [QuotationController::class, 'store'])->name('quotations.store');
                Route::get('quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
                Route::get('quotations/{quotation}/edit', [QuotationController::class, 'edit'])->name('quotations.edit');
                Route::put('quotations/{quotation}', [QuotationController::class, 'update'])->name('quotations.update');

                Route::post('quotations/{quotation}/send', [QuotationTransitionController::class, 'send'])->name('quotations.send');
                Route::post('quotations/{quotation}/accept', [QuotationTransitionController::class, 'accept'])->name('quotations.accept');
                Route::post('quotations/{quotation}/reject', [QuotationTransitionController::class, 'reject'])->name('quotations.reject');
                Route::post('quotations/{quotation}/expire', [QuotationTransitionController::class, 'expire'])->name('quotations.expire');
                Route::post('quotations/{quotation}/cancel', [QuotationTransitionController::class, 'cancel'])->name('quotations.cancel');
                Route::post('quotations/{quotation}/duplicate', [QuotationTransitionController::class, 'duplicate'])->name('quotations.duplicate');
            });
        });

    // Generated documents: reached only via a temporary SIGNED URL, then policy-
    // checked. Not behind `internal` so customer-facing docs can be served to
    // external reps later — GeneratedDocumentPolicy decides.
    Route::middleware(['auth', 'verified', 'signed'])
        ->name('tenant.')
        ->group(function () {
            Route::get('documents/{document}/download', [GeneratedDocumentController::class, 'download'])
                ->name('documents.download');
        });

    // Tasks & project boards. Reachable by BOTH internal staff and external
    // representatives (NO `internal` middleware) — every action is authorized by
    // BoardPolicy / TaskPolicy, which enforce external isolation server-side.
    Route::middleware(['auth', 'verified'])
        ->name('tenant.')
        ->group(function () {
            Route::get('boards', [BoardController::class, 'index'])->name('boards.index');
            Route::post('boards', [BoardController::class, 'store'])->name('boards.store');
            Route::get('boards/{board}', [BoardController::class, 'show'])->name('boards.show');
            Route::put('boards/{board}', [BoardController::class, 'update'])->name('boards.update');
            Route::delete('boards/{board}', [BoardController::class, 'destroy'])->name('boards.destroy');

            Route::post('boards/{board}/members', [BoardMemberController::class, 'store'])->name('boards.members.store');
            Route::delete('boards/{board}/members/{user}', [BoardMemberController::class, 'destroy'])->name('boards.members.destroy');

            // Board steps (columns). Management is internal-only (BoardPolicy).
            Route::post('boards/{board}/columns', [BoardColumnController::class, 'store'])->name('boards.columns.store');
            Route::post('boards/{board}/columns/reorder', [BoardColumnController::class, 'reorder'])->name('boards.columns.reorder');
            Route::put('columns/{column}', [BoardColumnController::class, 'update'])->name('columns.update');
            Route::delete('columns/{column}', [BoardColumnController::class, 'destroy'])->name('columns.destroy');

            Route::post('boards/{board}/tasks', [TaskController::class, 'store'])->name('tasks.store');
            Route::put('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
            Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

            // Free movement — one endpoint, any column on the task's own board.
            Route::post('tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');

            Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
            Route::post('tasks/{task}/attachments', [TaskAttachmentController::class, 'store'])->name('tasks.attachments.store');
            Route::get('attachments/{attachment}/download', [TaskAttachmentController::class, 'download'])->name('tasks.attachments.download');
        });
});
