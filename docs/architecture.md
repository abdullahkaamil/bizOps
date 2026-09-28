# Architecture

## Modular monolith
BizOps is a single Laravel 13 application (not microservices). Business capabilities are separated into **domain modules** under `app/Domain/<Module>/` with their own `Actions, Data, Enums, Events, Models, Policies, Queries, Services`. Central SaaS code lives under `app/Central/`. The HTTP layer (`app/Http/{Controllers,Requests,Resources,Middleware}`) is thin — it validates, authorizes, and delegates to actions. Domain folders are used only for real business boundaries; simple CRUD is not over-layered.

> Current state: the tenancy foundation predates this layout and still lives in `app/Http/Controllers/Admin`, `app/Models`, and `routes/web.php`. It is aligned to the target structure opportunistically as modules are built.

## Request lifecycle
1. `InitializeTenancyBySubdomain` runs as **global** middleware (before the web group). On a tenant subdomain it initializes tenancy (switches the DB connection, cache prefix, filesystem, queue to the tenant). On a central domain it continues in central context via the `$onFail` handler.
2. The `web` middleware group runs: cookies → **session** (now tenant-aware) → CSRF → `EnsureTenantLicenseIsActive` → Inertia share.
3. Route/group middleware (`central`, `tenant`, `auth`, `verified`) gate the route.
4. Controller validates via a Form Request, authorizes via a Policy, delegates to an Action, returns an Inertia response.

Tenancy **must** initialize before the session starts so the authenticated session is read/written in the tenant database (see `docs/tenancy.md`).

## Central and tenant boundaries
- **Central domains**: `kaamil.test` (marketing/landing) and `dashboard.kaamil.test` (SaaS admin console). Data lives in the central `bizops_central` database (tenants, domains, central admins).
- **Tenant subdomains**: `{tenant}.kaamil.test`. Each tenant has its own database (`tenant<uuid>`) holding users, roles/permissions, activity log, and all business data.
- Normal tenant requests never reach another tenant's or the central business data.

## Vue / Inertia architecture
Server-rendered routing via Inertia; pages are Vue 3 SFCs (`<script setup lang="ts">`) in `resources/js/pages/`. Layouts are resolved centrally in `app.ts`. Permissions and tenant context are shared on every response and consumed via composables (e.g. `usePermissions`) for **presentation only** — never as security. Wayfinder generates typed route/action helpers.

## Queue architecture
Redis-backed queues (via `predis`). `QueueTenancyBootstrapper` tags queued jobs with the tenant so they re-initialize the correct tenant context on the worker. Tenant provisioning (create DB, migrate, seed) runs through a stancl `JobPipeline` (synchronous today; queue-able for production).

## Storage architecture
Local disk for MVP; S3-compatible object storage in production. Filesystem is tenant-aware via `FilesystemTenancyBootstrapper` (tenant files are suffixed per tenant). `asset()` is **not** tenant-scoped (Vite bundle stays global); use `tenant_asset()` for per-tenant files. Sensitive files are served via signed private URLs (planned per Documents module).

## PDF architecture
Planned: server-side rendering of Blade/HTML templates to PDF for job service reports, workshop reports, and quotations, stored on the tenant disk and delivered via signed URLs. Not yet implemented.

## Search architecture
Planned: tenant-scoped global search across customers, jobs, tickets, quotations. MVP uses Postgres queries/indexes; a dedicated search engine is deferred.

## Deployment topology
- App: Laravel (PHP 8.4) behind a web server; Valet locally (HTTPS), a PHP-FPM host in production.
- PostgreSQL 18 (central + per-tenant databases), Redis (cache, sessions-optional, queue).
- Queue worker process(es) with tenant-aware jobs; scheduler for expiry/automation.
- Local dev: Postgres + Redis in Docker; app via Valet at `kaamil.test`.
