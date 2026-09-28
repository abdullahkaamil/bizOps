# Backups & Disaster Recovery

Database-per-tenant means backups are per-tenant plus the central database.

## What is backed up
- **Central database** (tenants, domains, provisioning logs, support sessions,
  announcements, queue).
- **Each tenant database** — via `tenants:backup` (encrypted logical JSON snapshot
  through `BackupTenant`), scheduled nightly; production additionally uses
  `pg_dump` / managed PostgreSQL snapshots.
- **Object storage** (private files: photos, signatures, generated PDFs) — rely on
  bucket versioning + lifecycle in production.

## Properties
- **Encryption** — logical snapshots are encrypted at rest with the app key
  (verified by test). Managed snapshots use provider-side encryption.
- **Retention** — keep nightly for 30 days + weekly for a quarter (policy).
- **Restore is tested, not assumed** — `RestoreTenant` reloads a snapshot into the
  tenant database (FK triggers deferred via `session_replication_role`), and
  `tests/Tenancy/BackupTest.php` performs a backup → destroy → restore roundtrip.

## Procedures
- **Tenant-specific restore** — `RestoreTenant::handle($tenant, $snapshotPath)`
  (or restore a `pg_dump` into the tenant database). Used for accidental deletion or
  point-in-time recovery for one tenant.
- **Full-platform DR** — restore the central database, then restore each tenant
  database and re-point object storage; re-run `tenants:migrate` if schema drift is
  detected. Validate with a staging restore before every release.

> A backup is never considered valid until its restore has been exercised.
