<?php

use App\Central\Console\BackupTenants;
use App\Central\Console\MigrateTenantsBatched;
use App\Console\Commands\HealthCheck;
use App\Console\Commands\ValidateEnvironment;
use App\Domain\Notifications\Console\NotifyTasksDueSoon;
use App\Domain\Quotations\Console\ExpireQuotations;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureCentralDomain;
use App\Http\Middleware\EnsureInternalUser;
use App\Http\Middleware\EnsureTenantDomain;
use App\Http\Middleware\EnsureTenantFeature;
use App\Http\Middleware\EnsureTenantLicenseIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        NotifyTasksDueSoon::class,
        ExpireQuotations::class,
        BackupTenants::class,
        MigrateTenantsBatched::class,
        ValidateEnvironment::class,
        HealthCheck::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Identify the tenant by subdomain as GLOBAL middleware, so tenancy is
        // initialized before the web group's session/auth middleware run. This
        // makes the session (and the authenticated user it stores) read from and
        // written to the TENANT database — not the central one. On central
        // domains the middleware's $onFail handler (see TenancyServiceProvider)
        // lets the request continue in central context.
        $middleware->prepend(InitializeTenancyBySubdomain::class);

        // Correlation id + structured logging context (runs after tenancy init
        // so the active tenant is captured in the log context).
        $middleware->append(AssignRequestId::class);

        $middleware->web(append: [
            EnsureTenantLicenseIsActive::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'central' => EnsureCentralDomain::class,
            'tenant' => EnsureTenantDomain::class,
            'internal' => EnsureInternalUser::class,
            'feature' => EnsureTenantFeature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
