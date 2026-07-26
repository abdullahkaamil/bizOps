<?php

use App\Http\Middleware\EnsureCentralDomain;
use App\Http\Middleware\EnsureTenantDomain;
use App\Http\Middleware\EnsureTenantLicenseIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Identify the tenant by subdomain as GLOBAL middleware, so tenancy is
        // initialized before the web group's session/auth middleware run. This
        // makes the session (and the authenticated user it stores) read from and
        // written to the TENANT database — not the central one. On central
        // domains the middleware's $onFail handler (see TenancyServiceProvider)
        // lets the request continue in central context.
        $middleware->prepend(InitializeTenancyBySubdomain::class);

        $middleware->web(append: [
            EnsureTenantLicenseIsActive::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'central' => EnsureCentralDomain::class,
            'tenant' => EnsureTenantDomain::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
