# Tenancy

Package: `stancl/tenancy` v3, **database-per-tenant**, **subdomain identification**.

## Tenant definition
A tenant is a company subscribing to the SaaS. Each tenant owns an isolated database. External customer representatives are restricted **users inside a tenant**, not tenants themselves.

## Central tables (`bizops_central`)
- `tenants` (id UUID, name, `license_expires_at`, data JSON, timestamps)
- `domains` (subdomain → tenant)
- `users` (central SaaS administrators), plus password reset, sessions, cache, jobs
- spatie permission + activity_log tables (present centrally for a uniform User model)

## Tenant tables (per `tenant<uuid>` DB)
Migrated from `database/migrations/tenant/`: users (+ sessions, password resets), cache, jobs, passkeys, 2FA columns, spatie permission tables, activity_log — plus each business module's tables as they land.

## Identification method
`InitializeTenancyBySubdomain` registered as **global** middleware (`bootstrap/app.php`), so tenancy initializes before session/auth. Central domains (`kaamil.test`, `dashboard.kaamil.test`, `localhost`, `127.0.0.1`) fall through to central context via `$onFail` (a `NotASubdomainException` continues; an unknown subdomain 404s).

## Provisioning lifecycle
`App\Actions\Tenancy\CreateTenant`:
1. `Tenant::create()` fires `TenantCreated` → stancl `JobPipeline`: **CreateDatabase → MigrateDatabase**.
2. Attach the subdomain `Domain`.
3. `Tenant::run()`: seed roles/permissions (`TenantDatabaseSeeder`) and create the first admin user (Admin role).

## Database naming
`prefix + tenant_id` → `tenant<uuid>` on the same PostgreSQL server as central. (`CREATE DATABASE` cannot run inside a transaction — see testing notes.)

## Suspension (licensing)
Each tenant has `license_expires_at`. `EnsureTenantLicenseIsActive` redirects every request from an expired tenant to the `tenant.license.expired` notice until a central admin renews it. This is the MVP suspension mechanism; plans/subscriptions are a future extension.

## Archival
Deferred. Planned: mark a tenant archived (read-only or offline) without dropping its database; retain for the legal window before deletion.

## Tenant deletion
`Tenant::delete()` fires `TenantDeleted` → `DeleteDatabase` (drops the tenant DB). Guard rails and grace periods before hard deletion are a production concern (see retention, deferred).

## Backup and restore
Deferred (X4 in the decision register). Planned: per-tenant logical dumps and point-in-time restore of an individual tenant database; documented before production.
